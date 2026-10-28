<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Filament\Resources\Orders\Pages;

use App\Plugins\OrderManagement\src\Filament\Resources\Orders\OrderResource;
use App\Plugins\OrderManagement\src\Enums\OrderPosStatus;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

final class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resend_to_pos')
                ->label('إعادة الإرسال لنقطة البيع')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('إعادة الإرسال لنقطة البيع')
                ->modalDescription('هل أنت متأكد من إعادة إرسال هذا الطلب لنقطة البيع؟')
                ->modalSubmitActionLabel('تأكيد')
                ->action(function ($record) {
                    $record->update(['pos_status' => class_exists(OrderPosStatus::class) ? OrderPosStatus::READY : 'ready']);
                    Notification::make()->title('تم إعادة إرسال الطلب لنقطة البيع')->success()->send();
                })
                ->visible(fn ($record) => class_exists(OrderPosStatus::class) && ($record->pos_status === OrderPosStatus::FAILED || $record->pos_status === 'failed')),
        ];
    }
}
