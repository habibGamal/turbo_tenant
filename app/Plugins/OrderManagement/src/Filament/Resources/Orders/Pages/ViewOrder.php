<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Filament\Resources\Orders\Pages;

use App\Plugins\PosIntegration\src\Enums\OrderPosStatus;
use App\Plugins\OrderManagement\src\Filament\Resources\Orders\OrderResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('resend_to_pos')
                ->label('Resend to POS')
                ->requiresConfirmation()
                ->action(function ($record) {
                    $record->update(['pos_status' => OrderPosStatus::READY]);
                    Notification::make()->title('Order sent to POS')->success()->send();
                })
                ->visible(fn ($record) => $record->pos_status === OrderPosStatus::FAILED),
        ];
    }
}
