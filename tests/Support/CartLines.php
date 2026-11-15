<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Plugins\CartEngine\src\DTOs\CartLine;
use App\Plugins\CartEngine\src\DTOs\CartLineExtra;

/**
 * Builds CartLine DTOs for tests that exercise orders/coupons without a real cart.
 */
final class CartLines
{
    /**
     * @param  list<CartLine>  $lines
     */
    public static function total(array $lines): float
    {
        return array_sum(array_map(fn (CartLine $line): float => $line->subtotal, $lines));
    }

    /**
     * @param  list<CartLineExtra>  $extras
     */
    public static function make(
        int $productId,
        float $subtotal = 100.0,
        ?int $categoryId = null,
        float $quantity = 1.0,
        ?float $unitPrice = null,
        string $productName = 'Test product',
        array $extras = [],
        int $itemId = 1,
    ): CartLine {
        return new CartLine(
            itemId: $itemId,
            productId: $productId,
            categoryId: $categoryId,
            variantId: null,
            weightOptionValueId: null,
            weightMultiplier: 1,
            quantity: $quantity,
            unitPrice: $unitPrice ?? $subtotal / max($quantity, 1.0),
            extrasUnitTotal: 0.0,
            subtotal: $subtotal,
            productName: $productName,
            productNameAr: null,
            variantName: null,
            variantNameAr: null,
            weightLabel: null,
            extras: $extras,
        );
    }
}
