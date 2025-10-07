<?php
// app/Http/Controllers/Api/NotificationsController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Resources\NotificationResource;
use App\Models\UserNotification;

class NotificationsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Option: paginate or return all. Default: paginated
        $perPage = (int) $request->query('per_page', 20);

        $q = $user->notifications()->orderByDesc('timestamp');

        return NotificationResource::collection($q->paginate($perPage));
    }

    public function unreadCount(Request $request)
    {
        $count = $request->user()->notifications()->where('read', false)->count();
        return response()->json(['unread' => $count]);
    }

    public function markAsRead(Request $request, $id)
    {
        $user = $request->user();
        $notification = $user->notifications()->where('id', $id)->firstOrFail();
        $notification->update(['read' => true]);
        return new NotificationResource($notification);
    }

    public function markAllRead(Request $request)
    {
        $user = $request->user();
        $user->notifications()->where('read', false)->update(['read' => true]);
        return response()->json(['success' => true]);
    }
}
