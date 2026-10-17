<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Pipelines\DTOs;

final readonly class PricingAdjustment
{
    public const TYPE_DISCOUNT = 'discount';
    public const TYPE_TAX = 'tax';
    public const TYPE_FEE = 'fee';
    public const TYPE_SHIPPING = 'shipping';
    public const TYPE_SURCHARGE = 'surcharge';

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $identifier,
        public string $label,
        public float $amount,
        public string $type,
        public array $metadata = []
    ) {}
}
