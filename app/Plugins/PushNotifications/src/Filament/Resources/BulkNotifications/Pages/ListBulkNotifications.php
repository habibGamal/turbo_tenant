<?php

declare(strict_types=1);

namespace App\Plugins\PushNotifications\src\Filament\Resources\BulkNotifications\Pages;

use App\Plugins\PushNotifications\src\Filament\Resources\BulkNotifications\BulkNotificationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBulkNotifications extends ListRecords
{
    protected static string $resource = BulkNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('إنشاء إشعار جماعي'),
        ];
    }
}
