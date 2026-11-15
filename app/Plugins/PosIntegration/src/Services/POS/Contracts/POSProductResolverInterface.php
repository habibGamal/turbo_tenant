<?php

declare(strict_types=1);

namespace App\Plugins\PosIntegration\src\Services\POS\Contracts;

use App\Plugins\OrderManagement\src\Models\OrderItem;
use Illuminate\Support\Collection;

interface POSProductResolverInterface
{
    /**
     * Return a new resolver instance with preloaded mappings.
     *
     * @param  Collection<int, \App\Plugins\PosIntegration\src\Models\ProductPosMapping>  $mappings
     */
    public function withMappings(Collection $mappings): static;

    /**
     * Resolve POS item structure for an order item according to posRefObj specifications.
     *
     * @return array{
     *     quantity: float|int,
     *     notes: string,
     *     posRefObj: array<int, array{productRef: string, quantity: float|int}>
     * }|null
     */
    public function resolveOrderItem(OrderItem $item, ?int $branchId): ?array;
}
