<?php
namespace App\Services;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Sending, storing and reading messages.
 *
 * Attachments are written to the private disk and served through a route that
 * checks membership. A voice note between two staff must not be readable by
 * anyone who guesses a filename.
 */
class ChatService
{
    /** Threads this person is in, most recent first. */
    public function conversationsFor(User $user, int $limit = 40)
    {
        return Conversation::whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
            ->with(['users:id,name,avatar', 'participants'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function unreadTotal(User $user): int
    {
        return (int) DB::table('messages as m')
            ->join('conversation_participants as p', 'p.conversation_id', '=', 'm.conversation_id')
            ->where('p.user_id', $user->id)
            ->where('m.user_id', '!=', $user->id)
            ->whereNull('m.deleted_at')
            ->where(function ($q) {
                $q->whereNull('p.last_read_at')->orWhereColumn('m.created_at', '>', 'p.last_read_at');
            })
            ->count();
    }

    public function isMember(Conversation $c, User $user): bool
    {
        return $c->participants()->where('user_id', $user->id)->exists();
    }

    /**
     * Messages in a thread.
     *
     * `after` returns only what has arrived since, which is what polling asks
     * for; without it the most recent page is returned, oldest-first for
     * rendering.
     */
    public function messages(Conversation $c, ?int $after = null, int $limit = 50)
    {
        $query = $c->messages()->with('sender:id,name,avatar')->orderBy('id');

        if ($after !== null) {
            return $query->where('id', '>', $after)->limit($limit)->get();
        }

        return $c->messages()->with('sender:id,name,avatar')
            ->orderByDesc('id')->limit($limit)->get()->reverse()->values();
    }

    /**
     * Say something, with or without a file attached.
     *
     * A message with neither words nor a file is not a message.
     */
    public function send(Conversation $c, User $sender, ?string $body, ?UploadedFile $file = null, ?int $duration = null): ?Message
    {
        $body = trim((string) $body) ?: null;

        if (! $body && ! $file) {
            return null;
        }

        $attributes = [
            'conversation_id' => $c->id,
            'user_id'         => $sender->id,
            'type'            => Message::TEXT,
            'body'            => $body,
        ];

        if ($file) {
            $type = Message::typeForMime($file->getMimeType());
            $limit = Message::LIMITS[$type] ?? Message::LIMITS[Message::FILE];

            if ($file->getSize() > $limit['bytes']) {
                throw new \RuntimeException(sprintf(
                    'That %s is %s; the limit is %s.',
                    $type,
                    $this->human($file->getSize()),
                    $this->human($limit['bytes'])
                ));
            }

            // Stored under a random name on the private disk: the original name
            // is kept as a label, not as a path, so a file called
            // "../../.env" cannot become one.
            $path = $file->store('chat/'.$c->id, 'local');

            // array_merge, not +=. The union operator keeps the left-hand
            // value for a duplicate key, so 'type' stayed 'text' while the
            // brand-new keys went through - a photo stored its file and then
            // rendered as an empty text message.
            $attributes = array_merge($attributes, [
                'type'            => $type,
                'attachment_path' => $path,
                'attachment_name' => mb_substr($file->getClientOriginalName(), 0, 180),
                'attachment_mime' => $file->getMimeType(),
                'attachment_size' => $file->getSize(),
                'duration'        => $duration,
            ]);
        }

        $message = Message::create($attributes);

        $c->update([
            'last_message_at'      => $message->created_at,
            'last_message_preview' => $message->preview(),
        ]);

        // The sender has read their own message by definition.
        ConversationParticipant::where('conversation_id', $c->id)
            ->where('user_id', $sender->id)
            ->update(['last_read_at' => now()]);

        $this->notifyOthers($c, $message, $sender);

        return $message->load('sender:id,name,avatar');
    }

    public function markRead(Conversation $c, User $user): void
    {
        ConversationParticipant::where('conversation_id', $c->id)
            ->where('user_id', $user->id)
            ->update(['last_read_at' => now()]);
    }

    /**
     * A bell notification, not an email.
     *
     * Chat is a conversation; an email for every line would be unusable, and
     * this host sends synchronously so it would also be slow. Muted threads say
     * nothing at all.
     */
    private function notifyOthers(Conversation $c, Message $message, User $sender): void
    {
        $others = ConversationParticipant::where('conversation_id', $c->id)
            ->where('user_id', '!=', $sender->id)
            ->where('muted', false)
            ->pluck('user_id');

        foreach ($others as $userId) {
            // One unread notice per thread, refreshed, rather than one per line.
            $existing = Notification::where('user_id', $userId)
                ->where('type', 'chat')
                ->whereNull('read_at')
                ->where('data->conversation_id', $c->id)
                ->first();

            $body = $sender->name.': '.$message->preview();

            if ($existing) {
                $existing->update(['body' => $body, 'created_at' => now()]);
                continue;
            }

            Notification::create([
                'user_id' => $userId,
                'type'    => 'chat',
                'title'   => 'New message',
                'body'    => $body,
                'data'    => ['conversation_id' => $c->id],
            ]);
        }
    }

    private function human(int $bytes): string
    {
        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($bytes < 1024) {
                return round($bytes, $unit === 'B' ? 0 : 1).' '.$unit;
            }
            $bytes /= 1024;
        }

        return round($bytes, 1).' TB';
    }
}
