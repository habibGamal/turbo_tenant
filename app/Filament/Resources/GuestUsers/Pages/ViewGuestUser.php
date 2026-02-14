<?php

declare(strict_types=1);

namespace App\Filament\Resources\GuestUsers\Pages;

use App\Filament\Resources\GuestUsers\GuestUserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;

final class ViewGuestUser extends ViewRecord
{
    protected static string $resource = GuestUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
