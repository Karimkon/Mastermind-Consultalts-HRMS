<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Staff messaging, for the mobile app.
 *
 * The same conversations, the same service and the same rules as the panel on
 * the web: only the way the caller proves who they are differs, and the URL an
 * attachment is fetched from, because a bearer token cannot be used against a
 * route that expects a session cookie.
 */
class ChatApiController extends Controller
{
    public function __construct(private ChatService $chat) {}

    /** Threads, with unread counts. */
    public function index(Request $request)
    {
        $me = $request->user();
        $this->chat->touchDelivery($me);

        $conversations = $this->chat->conversationsFor($me)->map(fn (Conversation $c) => [
            'id'      => $c->id,
            'type'    => $c->type,
            'title'   => $c->titleFor($me->id),
            'avatar'  => $c->type === Conversation::DIRECT
                            ? $c->otherUsers($me->id)->first()?->avatar_url
                            : null,
            'preview' => $c->last_message_preview,
            'at'      => $c->last_message_at?->toIso8601String(),
            'unread'  => $c->unreadFor($me->id),
            'members' => $c->users->count(),
        ]);

        return response()->json([
            'conversations' => $conversations,
            'unread'        => $this->chat->unreadTotal($me),
        ]);
    }

    /** Just the badge. */
    public function unread(Request $request)
    {
        $this->chat->touchDelivery($request->user());

        return response()->json(['unread' => $this->chat->unreadTotal($request->user())]);
    }

    public function messages(Request $request, Conversation $conversation)
    {
        $me = $request->user();
        abort_unless($this->chat->isMember($conversation, $me), 403);

        // has(), not filled(): "0" is empty in PHP, so the first poll on a fresh
        // thread would look like an open and mark everything read unseen.
        $after = $request->has('after') ? (int) $request->query('after') : null;

        $this->chat->touchDelivery($me);

        $messages = $this->chat->messages($conversation, $after);

        if ($after === null) {
            $this->chat->markRead($conversation, $me);
        }

        return response()->json([
            'title'    => $conversation->titleFor($me->id),
            'marks'    => $this->chat->receiptMarks($conversation, $me->id),
            'messages' => $messages->map(fn (Message $m) => $this->shape($m, $me->id))->values(),
        ]);
    }

    public function send(Request $request, Conversation $conversation)
    {
        $me = $request->user();
        abort_unless($this->chat->isMember($conversation, $me), 403);

        $request->validate([
            'body'       => 'nullable|string|max:5000',
            'attachment' => 'nullable|file|max:65536',   // 64MB, the video ceiling
            'duration'   => 'nullable|integer|min:0|max:86400',
            'kind'       => 'nullable|in:audio,video,image,file',
        ]);

        try {
            $message = $this->chat->send(
                $conversation,
                $me,
                $request->input('body'),
                $request->file('attachment'),
                $request->integer('duration') ?: null,
                $request->input('kind')
            );
        } catch (\RuntimeException $e) {
            // Size and type refusals are the sender's business, not a 500.
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if (! $message) {
            return response()->json(['message' => 'Nothing to send.'], 422);
        }

        return response()->json([
            'message' => $this->shape($message, $me->id),
            'marks'   => $this->chat->receiptMarks($conversation, $me->id),
        ], 201);
    }

    /** Start, or reopen, a one-to-one thread. */
    public function withUser(Request $request, User $user)
    {
        $me = $request->user();

        if ($user->id === $me->id) {
            return response()->json(['message' => 'You cannot message yourself.'], 422);
        }

        $conversation = Conversation::between($me->id, $user->id);

        return response()->json(['conversation_id' => $conversation->id, 'title' => $user->name]);
    }

    /** Who can be written to. */
    public function contacts(Request $request)
    {
        $me = $request->user();
        $term = trim((string) $request->query('q'));

        $users = User::where('status', 'active')
            ->where('id', '!=', $me->id)
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")))
            ->orderBy('name')
            ->limit(30)
            ->get(['id', 'name', 'email', 'avatar']);

        return response()->json([
            'contacts' => $users->map(fn (User $u) => [
                'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'avatar' => $u->avatar_url,
            ]),
        ]);
    }

    /**
     * Serve an attachment to somebody in the conversation.
     *
     * Never a public path: membership is checked on every fetch, so a voice
     * note cannot be read by anyone who comes by the URL.
     */
    public function attachment(Request $request, Message $message)
    {
        abort_unless($message->hasAttachment(), 404);
        abort_unless($this->chat->isMember($message->conversation, $request->user()), 403);
        abort_unless(Storage::disk('local')->exists($message->attachment_path), 404);

        $inline = in_array($message->type, [Message::IMAGE, Message::AUDIO, Message::VIDEO], true);

        return response()->file(
            Storage::disk('local')->path($message->attachment_path),
            [
                'Content-Type'        => $message->attachment_mime ?: 'application/octet-stream',
                'Content-Disposition' => ($inline ? 'inline' : 'attachment')
                    .'; filename="'.addslashes($message->attachment_name ?: 'file').'"',
            ]
        );
    }

    public function destroy(Request $request, Message $message)
    {
        // Your own words only. An administrator tidying somebody else's thread
        // is a different decision from taking back what you said.
        abort_unless($message->user_id === $request->user()->id, 403);

        $message->delete();

        return response()->json(['ok' => true]);
    }

    private function shape(Message $m, int $meId): array
    {
        return [
            'id'        => $m->id,
            'mine'      => $m->user_id === $meId,
            // Seconds, compared against the delivery and read watermarks to
            // draw the ticks, so a line already on screen can change without
            // being fetched again.
            'ts'        => $m->created_at?->getTimestamp(),
            'sender'    => $m->sender?->name,
            'avatar'    => $m->sender?->avatar_url,
            'type'      => $m->type,
            'body'      => $m->body,
            // The app carries a bearer token, so it cannot use the web route.
            'url'       => $m->hasAttachment()
                            ? url('/api/chat/attachments/'.$m->id)
                            : null,
            'file_name' => $m->attachment_name,
            'file_size' => $m->humanSize(),
            'mime'      => $m->attachment_mime,
            'duration'  => $m->duration,
            'at'        => $m->created_at?->format('H:i'),
            'on'        => $m->created_at?->format('d M Y'),
        ];
    }
}
