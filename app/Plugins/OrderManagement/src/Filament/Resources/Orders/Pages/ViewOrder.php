<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Filament\Resources\Orders\Pages;

use App\Core\Contracts\SettingsRepository;
use App\Plugins\OrderManagement\src\Enums\OrderStatus;
use App\Plugins\OrderManagement\src\Filament\Resources\Orders\OrderResource;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\OrderManagement\src\Settings\OrderSettingKey;
use App\Plugins\PosIntegration\src\Filament\Actions\OrderDeliveryActions;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

final class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    public function refreshPosDelivery(): void
    {
        $this->authorizeAccess();
        if (OrderDeliveryActions::enabled()) {
            $this->getRecord()->refresh()->load('posDelivery');
        }
    }

    protected function getHeaderActions(): array
    {
        $allowManualUpdate = (bool) app(SettingsRepository::class)->get(
            OrderSettingKey::ALLOW_MANUAL_ORDER_STATUS_UPDATE,
            false
        );

        return [
            Action::make('updateStatus')
                ->label('تحديث حالة الطلب (يدوي)')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn () => $allowManualUpdate)
                ->form([
                    Select::make('status')
                        ->label('الحالة الجديدة')
                        ->options(OrderStatus::class)
                        ->required(),
                    TextInput::make('reason')
                        ->label('سبب التعديل اليدوي')
                        ->required()
                        ->helperText('يتم تسجيل هذا التعديل لتدقيق الفروقات مع نظام POS'),
                ])
                ->action(function (array $data, Order $record): void {
                    $record->update(['status' => $data['status']]);

                    Notification::make()
                        ->title('تم تحديث حالة الطلب')
                        ->success()
                        ->send();
                }),

            OrderDeliveryActions::send(),
            OrderDeliveryActions::send(retry: true),
            OrderDeliveryActions::verify(),
        ];
    }
}
