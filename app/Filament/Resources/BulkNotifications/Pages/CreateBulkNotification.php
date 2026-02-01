<?php

declare(strict_types=1);

namespace App\Filament\Resources\BulkNotifications\Pages;

use App\Enums\BulkNotificationStatus;
use App\Filament\Resources\BulkNotifications\BulkNotificationResource;
use App\Jobs\SendBulkNotificationJob;
use Filament\Resources\Pages\CreateRecord;

final class CreateBulkNotification extends CreateRecord
{
    protected static string $resource = BulkNotificationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Remove helper fields
        $sendToAll = $data['send_to_all'] ?? true;
        $sendType = $data['send_type'] ?? 'immediate';

        unset($data['send_to_all'], $data['send_type']);

        // If sending to all, clear target_user_ids
        if ($sendToAll) {
            $data['target_user_ids'] = null;
        }

        // Set status based on send type
        if ($sendType === 'scheduled' && $data['scheduled_at']) {
            $data['status'] = BulkNotificationStatus::SCHEDULED;
        } else {
            $data['status'] = BulkNotificationStatus::DRAFT;
            $data['scheduled_at'] = null;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->record;

        // If not scheduled, dispatch immediately
        if ($record->status === BulkNotificationStatus::DRAFT) {
            SendBulkNotificationJob::dispatch($record);
        } elseif ($record->status === BulkNotificationStatus::SCHEDULED) {
            // Schedule the job for later
            SendBulkNotificationJob::dispatch($record)
                ->delay($record->scheduled_at);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
