<?php

declare(strict_types=1);

namespace App\Plugins\GuestCheckout\src\Filament\Resources\GuestUsers\Pages;

use App\Plugins\GuestCheckout\src\Filament\Resources\GuestUsers\GuestUserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;

class ViewGuestUser extends ViewRecord
{
    protected static string $resource = GuestUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
