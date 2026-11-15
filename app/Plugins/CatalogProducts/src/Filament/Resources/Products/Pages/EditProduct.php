<?php

declare(strict_types=1);

namespace App\Plugins\CatalogProducts\src\Filament\Resources\Products\Pages;

use App\Core\Plugins\Services\PluginContributions;
use App\Plugins\CatalogProducts\src\Contracts\ProductDeletionGuard;
use App\Plugins\CatalogProducts\src\Filament\Resources\Products\ProductResource;
use App\Plugins\CatalogProducts\src\Models\Product;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

final class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->before(function (DeleteAction $action): void {
                    /** @var Product $record */
                    $record = $this->getRecord();
                    $guards = $this->getDeletionGuards();

                    foreach ($guards as $guard) {
                        if ($guard instanceof ProductDeletionGuard) {
                            $reason = $guard->blockingReason($record);
                            if ($reason !== null) {
                                Notification::make()
                                    ->danger()
                                    ->title($reason)
                                    ->body($reason)
                                    ->send();

                                $action->halt();

                                return;
                            }
                        }
                    }
                }),
        ];
    }

    /**
     * @return iterable<mixed>
     */
    private function getDeletionGuards(): iterable
    {
        if (app()->bound(PluginContributions::class) && tenant() !== null) {
            return app(PluginContributions::class)->active('catalog-products.product-deletion-guards');
        }

        return app()->tagged('catalog-products.product-deletion-guards');
    }
}
