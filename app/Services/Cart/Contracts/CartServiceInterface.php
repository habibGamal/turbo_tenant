<?php

declare(strict_types=1);

namespace App\Services\Cart\Contracts;

use App\Models\GuestUser;
use App\Models\User;

interface CartServiceInterface
{
    public function getCartCount(User|GuestUser|null $user): int;

    public function getCart(User|GuestUser|null $user): array;

    public function addItem(
        User|GuestUser|null $user,
        int $productId,
        ?int $variantId,
        string $quantity,
        array $extras = [],
        ?int $weightOptionValueId = null,
        int $weightMultiplier = 1
    ): array;

    public function updateItem(
        User|GuestUser|null $user,
        string|int $itemId,
        ?string $quantity = null,
        ?int $weightMultiplier = null,
        ?int $weightOptionValueId = null
    ): array;

    public function removeItem(User|GuestUser|null $user, string|int $itemId): array;

    public function clearCart(User|GuestUser|null $user): array;

    public function syncGuestCartToUser(User $user): void;
}
