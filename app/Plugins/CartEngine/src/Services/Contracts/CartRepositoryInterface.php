<?php

declare(strict_types=1);

namespace App\Plugins\CartEngine\src\Services\Contracts;

use App\Plugins\CartEngine\src\Models\Cart;
use App\Plugins\CartEngine\src\Models\CartItem;

interface CartRepositoryInterface
{
    public function findCartWithRelations(string $column, mixed $value): ?Cart;

    public function countCartItems(?Cart $cart): int;

    public function findCartItemOrFail(int $itemId): CartItem;

    public function createCartItem(
        Cart $cart,
        int $productId,
        ?int $variantId,
        string $quantity,
        array $extras = [],
        ?int $weightOptionValueId = null,
        int $weightMultiplier = 1
    ): CartItem;

    public function updateCartItem(CartItem $item, array $attributes): CartItem;

    public function deleteCartItem(CartItem $item): void;

    public function deleteCart(Cart $cart): void;

    public function touchCart(Cart $cart): void;

    public function migrateCart(Cart $fromCart, Cart $toCart): void;
}
