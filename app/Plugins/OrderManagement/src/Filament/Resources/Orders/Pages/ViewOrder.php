<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Filament\Resources\Orders\Pages;

use App\Enums\OrderStatus;
use App\Enums\SettingKey;
use App\Plugins\OrderManagement\src\Enums\OrderPosStatus;
use App\Plugins\OrderManagement\src\Filament\Resources\Orders\OrderResource;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\StoreSettings\src\Services\SettingService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

final class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        $allowManualUpdate = filter_var(
            app(SettingService::class)->get(SettingKey::ALLOW_MANUAL_ORDER_STATUS_UPDATE, false),
            FILTER_VALIDATE_BOOLEAN
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

            Action::make('resend_to_pos')
                ->label('إعادة الإرسال لنقطة البيع')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('إعادة الإرسال لنقطة البيع')
                ->modalDescription('هل أنت متأكد من إعادة إرسال هذا الطلب لنقطة البيع؟')
                ->modalSubmitActionLabel('تأكيد')
                ->action(function (Order $record): void {
                    $record->update(['pos_status' => class_exists(OrderPosStatus::class) ? OrderPosStatus::READY : 'ready']);
                    Notification::make()->title('تم إعادة إرسال الطلب لنقطة البيع')->success()->send();
                })
                ->visible(fn (Order $record) => class_exists(OrderPosStatus::class) && ($record->pos_status === OrderPosStatus::FAILED || $record->pos_status === 'failed')),
        ];
    }
}
