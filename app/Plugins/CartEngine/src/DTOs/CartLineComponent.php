<?php

declare(strict_types=1);

namespace App\Plugins\CartEngine\src\DTOs;

/**
 * One product inside a composite cart line (e.g. the burger inside a combo).
 * Quantities and prices are per ONE unit of the parent line. $extras are charged once per
 * parent unit (Σ unitPrice × quantity), on top of $unitPriceAdjustment.
 */
final readonly class CartLineComponent
{
    /**
     * @param  list<CartLineExtra>  $extras
     */
    public function __construct(
        public ?int $sourceId,
        public ?string $groupName,
        public ?string $groupNameAr,
        public int $productId,
        public ?int $variantId,
        public string $productName,
        public ?string $productNameAr,
        public ?string $variantName,
        public ?string $variantNameAr,
        public float $quantity,
        public float $unitPriceAdjustment,
        public int $sortOrder = 0,
        public array $extras = [],
        public ?string $notes = null
    ) {}

    public function extrasTotal(): float
    {
        $total = 0.0;
        foreach ($this->extras as $extra) {
            $total += $extra->unitPrice * $extra->quantity;
        }

        return $total;
    }
}
