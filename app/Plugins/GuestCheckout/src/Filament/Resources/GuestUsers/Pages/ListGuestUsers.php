<?php

declare(strict_types=1);

namespace App\Plugins\GuestCheckout\src\Filament\Resources\GuestUsers\Pages;

use App\Plugins\GuestCheckout\src\Filament\Resources\GuestUsers\GuestUserResource;
use Filament\Resources\Pages\ListRecords;

class ListGuestUsers extends ListRecords
{
    protected static string $resource = GuestUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
