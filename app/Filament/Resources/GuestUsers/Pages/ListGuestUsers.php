<?php

declare(strict_types=1);

namespace App\Filament\Resources\GuestUsers\Pages;

use App\Filament\Resources\GuestUsers\GuestUserResource;
use Filament\Resources\Pages\ListRecords;

final class ListGuestUsers extends ListRecords
{
    protected static string $resource = GuestUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
