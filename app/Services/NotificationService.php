<?php
namespace App\Services;

use App\Models\UserNotification;
use App\Models\User;
use Carbon\Carbon;

class NotificationService
{
    /**
     * Create a notification for a user.
     * $data keys: title, message, type, timestamp (Carbon|datetime), avatar, related_view (array), action_data (array), category
     */
    public function createForUser(User|int $user, array $data): UserNotification
    {
        $userId = $user instanceof User ? $user->id : $user;

        $now = Carbon::now();
        $timestamp = $data['timestamp'] ?? $now;

        $notification = UserNotification::create([
            'user_id' => $userId,
            'title' => $data['title'] ?? 'Notification',
            'message' => $data['message'] ?? null,
            'type' => $data['type'] ?? 'info',
            'timestamp' => $timestamp,
            'read' => $data['read'] ?? false,
            'avatar' => $data['avatar'] ?? null,
            'related_view' => $data['related_view'] ?? null,
            'action_data' => $data['action_data'] ?? null,
            'category' => $data['category'] ?? 'system',
        ]);

        // optionally: fire an event or broadcast here if you later implement real-time push
        return $notification;
    }
}
