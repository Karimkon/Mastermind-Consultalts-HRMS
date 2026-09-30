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
     * Roles that may open a group.
     *
     * Anybody can be put in one, but starting a thread that writes to a hundred
     * phones at once is a different act from messaging a colleague, so it sits
     * with the people who already carry responsibility for a group of staff.
     */
    public const CAN_CREATE_GROUPS = [
        'super-admin', 'hr-admin', 'md', 'payroll-officer', 'manager', 'account-manager',
    ];

    public function canCreateGroups(User $user): bool
    {
        return $user->hasAnyRole(self::CAN_CREATE_GROUPS);
    }

    /**
     * Open a group and put people in it.
     *
     * One message reaches everybody, instead of the same sentence typed into
     * ninety separate threads. The creator is always a member — a group you
     * cannot see is a group you cannot follow up.
     *
     * @param  array<int,int>  $userIds
     */
    public function createGroup(User $creator, string $name, array $userIds): Conversation
    {
        $wanted = collect($userIds)->map(fn ($id) => (int) $id)->push($creator->id)->unique();

        // Only real, active accounts. A participant row pointing at nobody
        // leaves a group whose member count never matches who can read it.
        $members = User::whereIn('id', $wanted)->where('status', 'active')->pluck('id');

        $conversation = Conversation::create([
            'type'       => Conversation::GROUP,
            'name'       => trim($name),
            'created_by' => $creator->id,
        ]);

        $this->attach($conversation, $members);

        return $conversation->fresh();
    }

    /**
     * Add people to a group, skipping anybody already in it.
     *
     * @param  array<int,int>  $userIds
     * @return int  how many were actually added
     */
    public function addToGroup(Conversation $c, array $userIds): int
    {
        if ($c->type !== Conversation::GROUP) return 0;

        $toAdd = User::whereIn('id', $userIds)
            ->where('status', 'active')
            ->whereNotIn('id', $c->participants()->pluck('user_id'))
            ->pluck('id');

        $this->attach($c, $toAdd);

        return $toAdd->count();
    }

    private function attach(Conversation $c, $userIds): void
    {
        if ($userIds->isEmpty()) return;

        $now = now();
        ConversationParticipant::insert($userIds->map(fn ($id) => [
            'conversation_id' => $c->id,
            'user_id'         => $id,
            'created_at'      => $now,
            'updated_at'      => $now,
        ])->all());
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
    public function send(Conversation $c, User $sender, ?string $body, ?UploadedFile $file = null, ?int $duration = null, ?string $kind = null): ?Message
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
            $mime = $file->getMimeType() ?: $file->getClientMimeType();
            $type = Message::typeForMime($mime);

            // A recording is a WebM or MP4 container holding only sound. The
            // bytes look exactly like a video to the sniffer, so where the
            // container is ambiguous the panel's own word decides - it knows
            // whether it opened a microphone or a camera.
            if ($kind && Message::containerIsAmbiguous($mime)
                && in_array($kind, [Message::AUDIO, Message::VIDEO], true)) {
                $type = $kind;
                $mime = Message::mimeAs($mime, $type);
            }

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
                'attachment_mime' => $mime,
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
            ->update(['last_read_at' => now(), 'last_delivered_at' => now()]);
    }

    /**
     * Note that this person's client is listening.
     *
     * Called on every chat poll, across all their threads at once: if their
     * browser is asking for messages now, then everything sent before now has
     * reached them, whichever thread it was in. Without a websocket this is the
     * only honest meaning "delivered" can carry.
     */
    public function touchDelivery(User $user): void
    {
        ConversationParticipant::where('user_id', $user->id)
            ->update(['last_delivered_at' => now()]);
    }

    /**
     * The two watermarks a receipt is read off.
     *
     * Worked out once per request rather than once per message: a thread of
     * fifty lines would otherwise ask the same question fifty times.
     *
     * Both are the *earliest* across everybody else, so in a group the second
     * tick waits for the last person to receive it and the blue one for the
     * last person to read it - which is what two ticks are taken to mean.
     */
    public function receiptMarks(Conversation $c, int $meId): array
    {
        $others = $c->participants()->where('user_id', '!=', $meId)->get();

        if ($others->isEmpty()) {
            return ['delivered' => null, 'read' => null];
        }

        // Somebody who has read a thread has plainly received it, whatever the
        // delivery column says - it is only ever written by a poll.
        $delivered = $others->map(fn ($p) => max(
            $p->last_delivered_at?->getTimestamp() ?? 0,
            $p->last_read_at?->getTimestamp() ?? 0
        ));

        $read = $others->map(fn ($p) => $p->last_read_at?->getTimestamp() ?? 0);

        return [
            'delivered' => $delivered->min() ?: null,
            'read'      => $read->min() ?: null,
        ];
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
