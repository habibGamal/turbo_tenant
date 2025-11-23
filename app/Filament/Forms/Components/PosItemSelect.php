<?php

declare(strict_types=1);

namespace App\Filament\Forms\Components;

use App\Services\ProductPOSImporterService;
use Exception;
use Filament\Forms\Components\Select;

final class PosItemSelect extends Select
{
    public static function make(?string $name = null): static
    {
        return parent::make($name)
            ->label('POS Item ID')
            ->required()
            ->options(function (): array {
                try {
                    $service = app(ProductPOSImporterService::class);
                    $newProducts = collect($service->getAllProductReferences())->flatMap(fn ($item) => $item['products']);

                    return $newProducts
                        ->pluck('name', 'productRef')
                        ->unique()
                        ->toArray();
                } catch (Exception $e) {
                    return [];
                }
            })
            ->searchable();
    }
}
