<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Listeners;

use App\Models\User;
use App\Plugins\OrderManagement\src\Events\OrderCreated;
use App\Plugins\OrderManagement\src\Filament\Resources\Orders\OrderResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Throwable;

final class NotifyAdminsOfNewOrder
{
    public function handle(OrderCreated $event): void
    {
        try {
            $order = $event->order;
            $admins = User::where('is_admin', true)->get();

            if ($admins->isEmpty()) {
                return;
            }

            $customerName = $order->getCustomerName();
            $total = $order->total;
            $body = "{$customerName} - {$total}";

            Notification::make()
                ->title('طلب جديد #'.$order->order_number)
                ->body($body)
                ->actions([
                    Action::make('view')
                        ->label('عرض')
                        ->url(OrderResource::getUrl('view', ['record' => $order])),
                ])
                ->sendToDatabase($admins);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
