<?php
namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Staff messaging.
 *
 * Everything here answers JSON: the chat lives in a panel on whatever page the
 * person is already on, so it never navigates away from their work.
 */
class ChatController extends Controller
{
    public function __construct(private ChatService $chat) {}

    /**
     * Open a group and put people in it.
     *
     * The point of the thing: an Account Manager writes one message and every
     * guard on their client's site gets it, instead of the same sentence typed
     * into ninety separate threads.
     */
    public function createGroup(Request $request)
    {
        $me = $request->user();

        abort_unless($this->chat->canCreateGroups($me), 403,
            'Your role cannot start a group. Ask HR or your manager to open one.');

        $data = $request->validate([
            'name'       => 'required|string|max:120',
            'user_ids'   => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
        ], [
            'name.required'     => 'Give the group a name, so people know what it is.',
            'user_ids.required' => 'Choose at least one person to put in it.',
        ]);

        $conversation = $this->chat->createGroup($me, $data['name'], $data['user_ids']);
        $count        = $conversation->participants()->count();

        return response()->json([
            'conversation_id' => $conversation->id,
            'title'           => $conversation->name,
            'members'         => $count,
            'message'         => "\"{$conversation->name}\" created with {$count} member(s).",
        ], 201);
    }

    /** Add more people to a group that already exists. */
    public function addMembers(Request $request, Conversation $conversation)
    {
        $me = $request->user();

        abort_unless($this->chat->isMember($conversation, $me), 403);
        abort_unless($this->chat->canCreateGroups($me), 403, 'Your role cannot change who is in a group.');
        abort_unless($conversation->type === Conversation::GROUP, 422, 'That is not a group.');

        $data = $request->validate([
            'user_ids'   => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        $added = $this->chat->addToGroup($conversation, $data['user_ids']);

        return response()->json([
            'added'   => $added,
            'members' => $conversation->participants()->count(),
            'message' => $added ? "{$added} person(s) added." : 'Everybody chosen was already in the group.',
        ]);
    }

    /**
     * The ready-made lists somebody would otherwise tick one by one.
     *
     * An Account Manager's are their clients; HR's are the departments. Picking
     * ninety people out of 1,247 by hand is how a feature like this goes unused.
     */
    public function audiences(Request $request)
    {
        $me = $request->user();
        abort_unless($this->chat->canCreateGroups($me), 403);

        $out = [];

        $memberIds = fn ($callback) => User::where('status', 'active')
            ->where('id', '!=', $me->id)
            ->whereHas('employee', $callback)
            ->pluck('id');

        // Clients this person manages — or all of them, for HR and admin.
        $clients = \App\Models\Client::query()
            ->when(
                $me->hasRole('account-manager') && ! $me->hasAnyRole(['super-admin', 'hr-admin']),
                fn ($q) => $q->where('account_manager_id', $me->id)
            )
            ->orderBy('company_name')->get();

        foreach ($clients as $client) {
            $ids = $memberIds(fn ($q) => $q->whereHas('clients', fn ($c) => $c->where('clients.id', $client->id)));
            if ($ids->isNotEmpty()) {
                $out[] = ['key' => 'client:' . $client->id, 'group' => 'Clients',
                          'label' => $client->company_name, 'count' => $ids->count(), 'ids' => $ids];
            }
        }

        if ($me->hasAnyRole(['super-admin', 'hr-admin', 'md', 'manager'])) {
            foreach (\App\Models\Department::orderBy('name')->get() as $department) {
                $ids = $memberIds(fn ($q) => $q->where('department_id', $department->id));
                if ($ids->isNotEmpty()) {
                    $out[] = ['key' => 'department:' . $department->id, 'group' => 'Departments',
                              'label' => $department->name, 'count' => $ids->count(), 'ids' => $ids];
                }
            }
        }

        // Whoever reports to this person, however far down the line.
        if ($me->employee) {
            $ids = $memberIds(fn ($q) => $q->whereIn('id', $me->employee->descendantIds()));
            if ($ids->isNotEmpty()) {
                $out[] = ['key' => 'my-team', 'group' => 'My team',
                          'label' => 'Everyone reporting to me', 'count' => $ids->count(), 'ids' => $ids];
            }
        }

        return response()->json(['audiences' => $out]);
    }

    /** Threads, with unread counts, for the panel. */
    public function index(Request $request)
    {
        $me = $request->user();
        $this->chat->touchDelivery($me);

        $conversations = $this->chat->conversationsFor($me)->map(fn (Conversation $c) => [
            'id'         => $c->id,
            'type'       => $c->type,
            'title'      => $c->titleFor($me->id),
            'avatar'     => $c->type === Conversation::DIRECT
                                ? $c->otherUsers($me->id)->first()?->avatar_url
                                : null,
            'preview'    => $c->last_message_preview,
            'at'         => $c->last_message_at?->diffForHumans(),
            'unread'     => $c->unreadFor($me->id),
            'members'    => $c->users->count(),
        ]);

        return response()->json([
            'conversations' => $conversations,
            'unread'        => $this->chat->unreadTotal($me),
        ]);
    }

    /** Just the badge, for the poll that runs on every page. */
    public function unread(Request $request)
    {
        // This one runs on every page, so it is the poll that most often proves
        // somebody is online and able to receive.
        $this->chat->touchDelivery($request->user());

        return response()->json(['unread' => $this->chat->unreadTotal($request->user())]);
    }

    public function messages(Request $request, Conversation $conversation)
    {
        $me = $request->user();
        abort_unless($this->chat->isMember($conversation, $me), 403);

        // has(), not filled(). filled() is false for "0" because "0" is empty
        // in PHP, so the very first poll on a fresh thread - ?after=0 - looked
        // like an open and marked everything read without anybody seeing it.
        $after = $request->has('after') ? (int) $request->query('after') : null;

        $this->chat->touchDelivery($me);

        $messages = $this->chat->messages($conversation, $after);

        // Opening a thread marks it read; polling for new lines does not, or a
        // message arriving while the panel sits in the background would be
        // counted as read without anybody seeing it.
        if ($after === null) {
            $this->chat->markRead($conversation, $me);
        }

        return response()->json([
            'title'    => $conversation->titleFor($me->id),
            // How far the others have got, so the panel can redraw every tick
            // it is already showing and not just the new lines.
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
            // What the panel believes it captured. Only consulted when the
            // container alone cannot say.
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
            // Size and type refusals are the user's business, not a 500.
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
     * Serve an attachment, to somebody in the conversation.
     *
     * Never a public path. Membership is checked on every fetch, so a voice
     * note cannot be read by anyone who comes by the URL.
     */
    public function attachment(Request $request, Message $message)
    {
        abort_unless($message->hasAttachment(), 404);
        abort_unless($this->chat->isMember($message->conversation, $request->user()), 403);
        abort_unless(Storage::disk('local')->exists($message->attachment_path), 404);

        // Inline for things a browser plays or shows; a download for the rest.
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
        // Your own words only. An administrator clearing up somebody else's
        // thread is a different decision from taking back what you said.
        abort_unless($message->user_id === $request->user()->id, 403);

        $message->delete();

        return response()->json(['ok' => true]);
    }

    private function shape(Message $m, int $meId): array
    {
        return [
            'id'        => $m->id,
            'mine'      => $m->user_id === $meId,
            // Compared against the watermarks to draw the tick. Seconds, not a
            // formatted time, so the comparison is not a parsing exercise.
            'ts'        => $m->created_at?->getTimestamp(),
            'sender'    => $m->sender?->name,
            'avatar'    => $m->sender?->avatar_url,
            'type'      => $m->type,
            'body'      => $m->body,
            'url'       => $m->attachmentUrl(),
            'file_name' => $m->attachment_name,
            'file_size' => $m->humanSize(),
            'duration'  => $m->duration,
            'at'        => $m->created_at?->format('H:i'),
            'on'        => $m->created_at?->format('d M Y'),
        ];
    }
}
