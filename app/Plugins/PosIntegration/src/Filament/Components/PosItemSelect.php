<?php

declare(strict_types=1);

namespace App\Plugins\PosIntegration\src\Filament\Components;

use App\Plugins\PosIntegration\src\Services\POS\Contracts\PosMasterClient;
use Filament\Forms\Components\Select;
use Throwable;

final class PosItemSelect extends Select
{
    public static function make(?string $name = null): static
    {
        return parent::make($name)
            ->label('POS Item ID')
            ->required()
            ->options(function (): array {
                try {
                    $client = app(PosMasterClient::class);
                    $newProducts = collect($client->getAllProductReferences())->flatMap(fn ($item) => $item['products']);

                    return $newProducts
                        ->pluck('name', 'productRef')
                        ->unique()
                        ->toArray();
                } catch (Throwable $e) {
                    return [];
                }
            })
            ->searchable();
    }
}
