<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GuestUser;
use App\Models\User;
use App\Services\Cart\Contracts\CartContextResolverInterface;
use App\Services\Cart\Contracts\CartItemFormatterInterface;
use App\Services\Cart\Contracts\CartRepositoryInterface;
use App\Services\Cart\Contracts\CartServiceInterface;
use App\Services\Cart\Contracts\ProductValidatorInterface;

final class CartService implements CartServiceInterface
{
    public function __construct(
        private readonly CartRepositoryInterface $repository,
        private readonly CartItemFormatterInterface $formatter,
        private readonly CartContextResolverInterface $contextResolver,
        private readonly ProductValidatorInterface $validator
    ) {}

    public function getCartCount(User|GuestUser|null $user): int
    {
        $cart = $this->contextResolver->resolveCart($user);

        return $this->repository->countCartItems($cart);
    }

    public function getCart(User|GuestUser|null $user): array
    {
        if ($user instanceof User) {
            $cart = $this->repository->findCartWithRelations('user_id', $user->id);
        } else {
            $sessionId = $this->contextResolver->getGuestCartIdentifier();
            $cart = $this->repository->findCartWithRelations('session_id', $sessionId);
        }

        return $this->formatter->formatCart($cart);
    }

    public function addItem(
        User|GuestUser|null $user,
        int $productId,
        ?int $variantId,
        string $quantity,
        array $extras = [],
        ?int $weightOptionValueId = null,
        int $weightMultiplier = 1
    ): array {
        $this->validator->ensureProductIsActive($productId);

        $cart = $this->contextResolver->resolveOrCreateCart($user);

        $this->repository->createCartItem(
            cart: $cart,
            productId: $productId,
            variantId: $variantId,
            quantity: $quantity,
            extras: $extras,
            weightOptionValueId: $weightOptionValueId,
            weightMultiplier: $weightMultiplier
        );

        return $this->getCart($user);
    }

    public function updateItem(
        User|GuestUser|null $user,
        string|int $itemId,
        ?string $quantity = null,
        ?int $weightMultiplier = null,
        ?int $weightOptionValueId = null
    ): array {
        $cartItem = $this->repository->findCartItemOrFail((int) $itemId);

        $updateData = [];

        if ($quantity !== null) {
            $updateData['quantity'] = $quantity;
        }

        if ($weightMultiplier !== null) {
            $updateData['weight_multiplier'] = $weightMultiplier;
        }

        if ($weightOptionValueId !== null) {
            $updateData['weight_option_value_id'] = $weightOptionValueId;
        }

        if (! empty($updateData)) {
            $this->repository->updateCartItem($cartItem, $updateData);
        }

        $targetUser = $user ?? $cartItem->cart->user;

        return $this->getCart($targetUser);
    }

    public function removeItem(User|GuestUser|null $user, string|int $itemId): array
    {
        $cartItem = $this->repository->findCartItemOrFail((int) $itemId);
        $targetUser = $user ?? $cartItem->cart->user;

        $this->repository->deleteCartItem($cartItem);

        return $this->getCart($targetUser);
    }

    public function clearCart(User|GuestUser|null $user): array
    {
        $cart = $this->contextResolver->resolveCart($user);

        if ($cart) {
            $this->repository->deleteCart($cart);
        }

        if (! ($user instanceof User)) {
            $this->contextResolver->clearGuestSession();
        }

        return ['items' => [], 'total' => 0];
    }

    public function syncGuestCartToUser(User $user): void
    {
        $guestSessionId = $this->contextResolver->getGuestCartIdentifier();
        $guestCart = $this->repository->findCartWithRelations('session_id', $guestSessionId);

        if (! $guestCart || $guestCart->items->isEmpty()) {
            return;
        }

        $userCart = $this->contextResolver->resolveOrCreateCart($user);

        $this->repository->migrateCart($guestCart, $userCart);

        $this->contextResolver->clearGuestSession();
    }
}
