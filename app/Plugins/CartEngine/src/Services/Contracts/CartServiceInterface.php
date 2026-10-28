<?php

declare(strict_types=1);

namespace App\Plugins\CartEngine\src\Services\Contracts;

use App\Core\Plugins\Contracts\GuestCustomer;
use App\Models\User;

interface CartServiceInterface
{
    public function getCartCount(User|GuestCustomer|null $user): int;

    public function getCart(User|GuestCustomer|null $user): array;

    public function addItem(
        User|GuestCustomer|null $user,
        int $productId,
        ?int $variantId,
        string $quantity,
        array $extras = [],
        ?int $weightOptionValueId = null,
        int $weightMultiplier = 1
    ): array;

    public function updateItem(
        User|GuestCustomer|null $user,
        string|int $itemId,
        ?string $quantity = null,
        ?int $weightMultiplier = null,
        ?int $weightOptionValueId = null
    ): array;

    public function removeItem(User|GuestCustomer|null $user, string|int $itemId): array;

    public function clearCart(User|GuestCustomer|null $user): array;

    public function syncGuestCartToUser(User $user): void;
}
