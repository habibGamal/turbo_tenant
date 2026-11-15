<?php

declare(strict_types=1);

namespace App\Plugins\CartEngine\src\DTOs;

/**
 * What a CartLineTypeHandler resolved from an add-to-cart payload, ready to persist.
 */
final readonly class PreparedCartLine
{
    /**
     * @param  int  $referenceId  id of the sold thing in the handler's own domain (e.g. package id), stored in cart_items.line_reference_id
     * @param  string  $signature  stable hash of the selections; identical signatures in one cart merge their quantity
     * @param  list<CartLineComponent>  $components
     */
    public function __construct(
        public string $type,
        public int $referenceId,
        public string $signature,
        public array $components
    ) {}
}
