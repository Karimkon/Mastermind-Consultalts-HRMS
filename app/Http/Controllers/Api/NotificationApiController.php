<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationApiController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = $request->unread_only ? $user->unreadNotifications() : $user->notifications();

        $notifications = $query->latest()->paginate(20);

        return response()->json([
            'data' => $notifications->through(fn ($n) => $this->formatNotification($n)),
            'unread_count' => $user->unreadNotifications()->count(),
            // Which menu item each unread notice belongs to, so the app can put
            // the number where the thing is rather than only on a bell.
            'areas' => Notification::unreadCountsFor($user),
        ]);
    }

    public function markRead(Request $request)
    {
        $user = $request->user();

        if ($request->id) {
            $user->notifications()->where('id', $request->id)->update(['read_at' => now()]);
        } elseif ($request->area) {
            // Opening a screen settles that screen's notices and nothing else —
            // the same rule the web applies when you navigate to the page.
            Notification::markAreaRead($user, (string) $request->area);
        } else {
            // Was `$user->unreadNotifications->markAsRead()`, which is a method
            // on Laravel's own DatabaseNotificationCollection. This project
            // overrides notifications() to point at its own table, so that
            // relation hands back a plain Eloquent collection and the call
            // threw — "Mark all read" on the phone was a 500 every time.
            $user->unreadNotifications()->update(['read_at' => now()]);
        }

        return response()->json([
            'message' => 'Marked as read.',
            'unread_count' => $user->unreadNotifications()->count(),
            'areas' => Notification::unreadCountsFor($user),
        ]);
    }

    public function destroy(Request $request, string $id)
    {
        $request->user()->notifications()->where('id', $id)->delete();

        return response()->json(['message' => 'Deleted.']);
    }

    /**
     * This used to send only id/type/data, so every notice arrived on the phone
     * with no words in it: `title` and `body` are columns on this project's own
     * notifications table, not keys inside `data`, which is where the app was
     * looking. It fell back to prettifying the type string, so a payslip notice
     * displayed as "payroll processed" with an empty message.
     */
    private function formatNotification(Notification $n): array
    {
        return [
            'id' => $n->id,
            'type' => $n->type,
            'area' => Notification::AREAS[$n->type] ?? null,
            'title' => $n->title,
            'body' => $n->body,
            'action_url' => $n->action_url ?: ($n->data['url'] ?? null),
            'data' => $n->data,
            /*
             | ISO 8601 WITH the offset, never 'Y-m-d H:i:s'.
             |
             | A bare "2026-10-13 14:26:00" carries no zone, and Dart's
             | DateTime.parse reads an unmarked string as LOCAL time. The server
             | stores UTC and the phone is on EAT, so a notice sent one minute
             | ago was read as 14:26 EAT against a clock saying 17:26 and
             | displayed as "3h ago". Every timestamp in the app was three hours
             | stale.
             |
             | toIso8601String() emits the offset, so the client knows the
             | instant rather than guessing. This also fixes phones that already
             | have the app installed, with no release needed.
             */
            'read_at' => $n->read_at?->toIso8601String(),
            'created_at' => $n->created_at?->toIso8601String(),
        ];
    }
}
