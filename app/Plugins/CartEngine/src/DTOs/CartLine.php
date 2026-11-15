<?php

declare(strict_types=1);

namespace App\Plugins\CartEngine\src\DTOs;

final readonly class CartLine
{
    /**
     * Product lines carry productId; composite lines (type !== 'product', e.g. a combo package)
     * have productId null, reuse productName/productNameAr/productImage for their own display name
     * and image, and list what they contain in $components.
     *
     * @param  list<CartLineExtra>  $extras
     * @param  list<CartLineComponent>  $components
     */
    public function __construct(
        public int $itemId,
        public ?int $productId,
        public ?int $categoryId,
        public ?int $variantId,
        public ?int $weightOptionValueId,
        public int $weightMultiplier,
        public float $quantity,
        public float $unitPrice,
        public float $extrasUnitTotal,
        public float $subtotal,
        public string $productName,
        public ?string $productNameAr,
        public ?string $variantName,
        public ?string $variantNameAr,
        public ?string $weightLabel,
        public ?string $notes = null,
        public array $extras = [],
        public ?string $productImage = null,
        public bool $sellByWeight = false,
        public ?string $weightValue = null,
        public string $type = 'product',
        public ?int $referenceId = null,
        public array $components = [],
        public bool $couponEligible = true
    ) {}
}
