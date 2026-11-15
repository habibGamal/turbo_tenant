<?php

declare(strict_types=1);

use App\Core\Plugins\Routing\PluginRoutes;
use App\Plugins\PushNotifications\src\Http\Controllers\Api\NotificationController;
use Illuminate\Support\Facades\Route;

PluginRoutes::tenant('push-notifications')->prefix('api/notifications')->name('api.notifications.')->group(function () {
    Route::middleware('auth')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::get('/unread-count', [NotificationController::class, 'unreadCount'])->name('unread-count');
        Route::post('/{id}/mark-as-read', [NotificationController::class, 'markAsRead'])->name('mark-as-read')->whereNumber('id');
        Route::post('/mark-all-as-read', [NotificationController::class, 'markAllAsRead'])->name('mark-all-as-read');
        Route::delete('/{id}', [NotificationController::class, 'destroy'])->name('destroy')->whereNumber('id');
        Route::post('/clear-read', [NotificationController::class, 'clearRead'])->name('clear-read');
    });
});
