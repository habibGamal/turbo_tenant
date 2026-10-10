<?php

declare(strict_types=1);

namespace App\Services\POS\Contracts;

use App\Models\OrderItem;

interface POSProductResolverInterface
{
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
