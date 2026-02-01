<?php

declare(strict_types=1);

namespace App\Filament\Resources\BulkNotifications\Pages;

use App\Enums\BulkNotificationStatus;
use App\Filament\Resources\BulkNotifications\BulkNotificationResource;
use App\Jobs\SendBulkNotificationJob;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

final class ViewBulkNotification extends ViewRecord
{
    protected static string $resource = BulkNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resend')
                ->label('إعادة الإرسال')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->modalHeading('إعادة إرسال الإشعار')
                ->modalDescription('هل أنت متأكد من رغبتك في إعادة إرسال هذا الإشعار لجميع المستخدمين المستهدفين؟')
                ->action(function () {
                    $this->record->update([
                        'status' => BulkNotificationStatus::DRAFT,
                        'sent_at' => null,
                        'total_recipients' => 0,
                        'successful_sends' => 0,
                        'failed_sends' => 0,
                    ]);

                    SendBulkNotificationJob::dispatch($this->record);

                    Notification::make()
                        ->title('تم إعادة إرسال الإشعار')
                        ->success()
                        ->send();
                })
                ->visible(fn () => in_array($this->record->status, [BulkNotificationStatus::SENT, BulkNotificationStatus::FAILED])),
        ];
    }
}
