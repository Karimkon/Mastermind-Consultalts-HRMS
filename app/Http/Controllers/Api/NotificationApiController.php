<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationApiController extends Controller
{
    public function index(Request $request)
    {
        $user  = $request->user();
        $query = $user->notifications();

        if ($request->unread_only) {
            $query = $user->unreadNotifications();
        }

        $notifications = $query->latest()->paginate(20);

        return response()->json([
            'data'         => $notifications->through(fn($n) => $this->formatNotification($n)),
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    public function markRead(Request $request)
    {
        $user = $request->user();

        if ($request->id) {
            $user->notifications()->where('id', $request->id)->update(['read_at' => now()]);
        } else {
            $user->unreadNotifications->markAsRead();
        }

        return response()->json(['message' => 'Marked as read.', 'unread_count' => $user->unreadNotifications()->count()]);
    }

    public function destroy(Request $request, string $id)
    {
        $request->user()->notifications()->where('id', $id)->delete();
        return response()->json(['message' => 'Deleted.']);
    }

    private function formatNotification(DatabaseNotification $n): array
    {
        return [
            'id'         => $n->id,
            'type'       => class_basename($n->type),
            'data'       => $n->data,
            'read_at'    => $n->read_at?->format('Y-m-d H:i:s'),
            'created_at' => $n->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
