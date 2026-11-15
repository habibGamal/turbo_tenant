<?php

declare(strict_types=1);

namespace App\Plugins\PushNotifications\src\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Plugins\PushNotifications\src\Http\Resources\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class NotificationController extends Controller
{
    /**
     * Display a listing of the user's notifications.
     */
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()->pushNotifications()
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return ApiResponse::paginated($notifications, NotificationResource::class);
    }

    /**
     * Get unread notifications count.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $count = $request->user()->pushNotifications()->unread()->count();

        return ApiResponse::success([
            'count' => $count,
        ]);
    }

    /**
     * Mark a notification as read.
     */
    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $notification = $request->user()->pushNotifications()
            ->findOrFail($id);

        $notification->markAsRead();

        return ApiResponse::success(
            new NotificationResource($notification),
            'Notification marked as read'
        );
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->pushNotifications()
            ->unread()
            ->update([
                'read' => true,
                'read_at' => now(),
            ]);

        return ApiResponse::success(message: 'All notifications marked as read');
    }

    /**
     * Delete a notification.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $notification = $request->user()->pushNotifications()
            ->findOrFail($id);

        $notification->delete();

        return ApiResponse::success(message: 'Notification deleted');
    }

    /**
     * Delete all read notifications.
     */
    public function clearRead(Request $request): JsonResponse
    {
        $request->user()->pushNotifications()
            ->read()
            ->delete();

        return ApiResponse::success(message: 'Read notifications cleared');
    }
}
