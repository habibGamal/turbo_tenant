<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Jobs\SendOrderStatusNotificationJob;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderStatusNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use NotificationChannels\Expo\ExpoPushToken;

beforeEach(function () {
    $this->user = User::factory()->create([
        'expo_token' => ExpoPushToken::make('ExponentPushToken[xxxxxxxxxxxxxxxxxxxxxx]'),
    ]);
});

it('dispatches order status notification job when order is created', function () {
    Queue::fake();

    Order::factory()->create([
        'user_id' => $this->user->id,
    ]);

    Queue::assertPushed(SendOrderStatusNotificationJob::class);
});

it('dispatches order status notification job when order status is updated', function () {
    Queue::fake();

    $order = Order::factory()->create([
        'user_id' => $this->user->id,
        'status' => OrderStatus::PENDING,
    ]);

    Queue::assertPushed(SendOrderStatusNotificationJob::class, 1);

    $order->update(['status' => OrderStatus::PREPARING]);

    Queue::assertPushed(SendOrderStatusNotificationJob::class, 2);
});

it('does not dispatch notification job when order status is not changed', function () {
    Queue::fake();

    $order = Order::factory()->create([
        'user_id' => $this->user->id,
        'status' => OrderStatus::PENDING,
    ]);

    Queue::assertPushed(SendOrderStatusNotificationJob::class, 1);

    $order->update(['note' => 'Updated note']);

    Queue::assertPushed(SendOrderStatusNotificationJob::class, 1);
});

it('sends expo notification when job is executed', function () {
    Notification::fake();

    $order = Order::factory()->create([
        'user_id' => $this->user->id,
        'status' => OrderStatus::PREPARING,
    ]);

    $job = new SendOrderStatusNotificationJob($order);
    $job->handle();

    Notification::assertSentTo(
        $this->user,
        OrderStatusNotification::class,
        function ($notification, $channels) use ($order) {
            return in_array('expo', $channels) &&
                   $notification->order->id === $order->id;
        }
    );
});

it('does not send notification if user has no expo token', function () {
    Notification::fake();

    $userWithoutToken = User::factory()->create([
        'expo_token' => null,
    ]);

    $order = Order::factory()->create([
        'user_id' => $userWithoutToken->id,
        'status' => OrderStatus::PREPARING,
    ]);

    $job = new SendOrderStatusNotificationJob($order);
    $job->handle();

    Notification::assertNotSentTo($userWithoutToken, OrderStatusNotification::class);
});

it('generates correct notification title for each status', function (OrderStatus $status, string $expectedTitle) {
    $order = Order::factory()->create([
        'user_id' => $this->user->id,
        'status' => $status,
        'order_number' => 'ORD-123',
    ]);

    $notification = new OrderStatusNotification($order);
    $message = $notification->toExpo($this->user);

    expect($message->getTitle())->toBe($expectedTitle);
})->with([
    [OrderStatus::PENDING, 'تم استلام طلبك'],
    [OrderStatus::PREPARING, 'جاري تحضير طلبك'],
    [OrderStatus::OUT_FOR_DELIVERY, 'طلبك في الطريق'],
    [OrderStatus::DELIVERED, 'تم توصيل طلبك'],
    [OrderStatus::CANCELLED, 'تم إلغاء طلبك'],
]);

it('includes order data in expo message', function () {
    $order = Order::factory()->create([
        'user_id' => $this->user->id,
        'status' => OrderStatus::PREPARING,
        'order_number' => 'ORD-123',
    ]);

    $notification = new OrderStatusNotification($order);
    $message = $notification->toExpo($this->user);

    $data = $message->getData();

    expect($data)
        ->toHaveKey('order_id', $order->id)
        ->toHaveKey('order_number', 'ORD-123')
        ->toHaveKey('status', 'processing');
});

it('sets high priority and sound for expo message', function () {
    $order = Order::factory()->create([
        'user_id' => $this->user->id,
        'status' => OrderStatus::PREPARING,
    ]);

    $notification = new OrderStatusNotification($order);
    $message = $notification->toExpo($this->user);

    expect($message->getPriority())->toBe('high')
        ->and($message->getSound())->toBe('default');
});
