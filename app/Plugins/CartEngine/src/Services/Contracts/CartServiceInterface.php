<?php

declare(strict_types=1);

namespace App\Plugins\CartEngine\src\Services\Contracts;

use App\Core\Plugins\Contracts\GuestCustomer;
use App\Models\User;
use App\Plugins\CartEngine\src\DTOs\CartLine;

interface CartServiceInterface
{
    public function getCartCount(User|GuestCustomer|null $user): int;

    /**
     * @return list<CartLine>
     */
    public function getLines(User|GuestCustomer|null $user): array;

    /**
     * @param  list<array{id: int, quantity: int}>  $extras
     * @return list<CartLine> the cart after the change
     */
    public function addItem(
        User|GuestCustomer|null $user,
        int $productId,
        ?int $variantId,
        string $quantity,
        array $extras = [],
        ?int $weightOptionValueId = null,
        int $weightMultiplier = 1
    ): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return list<CartLine> the cart after the change
     */
    public function addCompositeItem(
        User|GuestCustomer|null $user,
        string $type,
        array $payload,
        int $quantity,
        ?string $notes = null
    ): array;

    /**
     * @return list<CartLine> the cart after the change
     */
    public function updateItem(
        User|GuestCustomer|null $user,
        string|int $itemId,
        ?string $quantity = null,
        ?int $weightMultiplier = null,
        ?int $weightOptionValueId = null
    ): array;

    /**
     * @return list<CartLine> the cart after the change
     */
    public function removeItem(User|GuestCustomer|null $user, string|int $itemId): array;

    /**
     * @return list<CartLine> the cart after the change
     */
    public function clearCart(User|GuestCustomer|null $user): array;

    /**
     * Remove only lines that still match the purchased snapshot.
     *
     * @param  list<CartLine>  $purchasedLines
     * @return list<CartLine> the remaining cart
     */
    public function removePurchasedLines(User|GuestCustomer|null $user, array $purchasedLines): array;

    public function syncGuestCartToUser(User $user): void;
}
