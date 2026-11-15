<?php

declare(strict_types=1);

namespace App\Plugins\CartEngine\src\Services;

use App\Core\Plugins\Contracts\GuestCustomer;
use App\Models\User;
use App\Plugins\CartEngine\src\DTOs\CartLine;
use App\Plugins\CartEngine\src\DTOs\CartLineExtra;
use App\Plugins\CartEngine\src\Exceptions\CartItemNotFound;
use App\Plugins\CartEngine\src\Models\Cart;
use App\Plugins\CartEngine\src\Models\CartItem;
use App\Plugins\CartEngine\src\Services\Contracts\CartContextResolverInterface;
use App\Plugins\CartEngine\src\Services\Contracts\CartPriceCalculatorInterface;
use App\Plugins\CartEngine\src\Services\Contracts\CartRepositoryInterface;
use App\Plugins\CartEngine\src\Services\Contracts\CartServiceInterface;
use App\Plugins\CartEngine\src\Services\Contracts\ProductValidatorInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

final class CartService implements CartServiceInterface
{
    public function __construct(
        private readonly CartRepositoryInterface $repository,
        private readonly CartContextResolverInterface $contextResolver,
        private readonly ProductValidatorInterface $validator,
        private readonly CartPriceCalculatorInterface $calculator,
        private readonly CartLineTypeRegistry $lineTypeRegistry,
    ) {}

    public function getCartCount(User|GuestCustomer|null $user): int
    {
        $cart = $this->contextResolver->resolveCart($user);

        return $this->repository->countCartItems($cart);
    }

    /**
     * @return list<CartLine>
     */
    public function getLines(User|GuestCustomer|null $user): array
    {
        if ($user instanceof User) {
            $cart = $this->repository->findCartWithRelations('user_id', $user->id);
        } else {
            $sessionId = $this->contextResolver->getGuestCartIdentifier();
            $cart = $this->repository->findCartWithRelations('session_id', $sessionId);
        }

        if (! $cart) {
            return [];
        }

        $lines = [];
        foreach ($cart->items as $item) {
            $line = $this->buildCartLine($item);
            if ($line !== null) {
                $lines[] = $line;
            }
        }

        return $lines;
    }

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
    ): array {
        $this->validator->validateOptions($productId, $variantId, $weightOptionValueId, $extras);

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

        return $this->getLines($user);
    }

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
    ): array {
        $handler = $this->lineTypeRegistry->get($type);
        if (! $handler) {
            throw ValidationException::withMessages([
                'type' => ['Unsupported line type'],
            ]);
        }

        // The handler may enforce per-order limits, so it sees the quantity being added.
        $prepared = $handler->prepare([...$payload, 'quantity' => $quantity]);

        $cart = $this->contextResolver->resolveOrCreateCart($user);

        $this->repository->createCompositeCartItem(
            cart: $cart,
            type: $type,
            prepared: $prepared,
            quantity: $quantity,
            notes: $notes
        );

        return $this->getLines($user);
    }

    /**
     * @return list<CartLine> the cart after the change
     */
    public function updateItem(
        User|GuestCustomer|null $user,
        string|int $itemId,
        ?string $quantity = null,
        ?int $weightMultiplier = null,
        ?int $weightOptionValueId = null
    ): array {
        $cartItem = $this->findOwnedItemOrFail($user, $itemId);

        if (! $cartItem->isProductLine()) {
            if ($weightMultiplier !== null || $weightOptionValueId !== null) {
                throw ValidationException::withMessages([
                    'weight' => ['Composite lines do not support weight options.'],
                ]);
            }

            if ($quantity !== null) {
                if (! filter_var($quantity, FILTER_VALIDATE_INT) || (int) $quantity < 1) {
                    throw ValidationException::withMessages([
                        'quantity' => ['Quantity for composite lines must be an integer of at least 1.'],
                    ]);
                }
            }
        }

        if ($cartItem->isProductLine() && $weightOptionValueId !== null) {
            $this->validator->validateUpdateWeightOption((int) $cartItem->product_id, $weightOptionValueId);
        }

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

        return $this->getLines($targetUser);
    }

    /**
     * @return list<CartLine> the cart after the change
     */
    public function removeItem(User|GuestCustomer|null $user, string|int $itemId): array
    {
        $cartItem = $this->findOwnedItemOrFail($user, $itemId);

        $targetUser = $user ?? $cartItem->cart->user;

        $this->repository->deleteCartItem($cartItem);

        return $this->getLines($targetUser);
    }

    /**
     * @return list<CartLine> the cart after the change
     */
    public function clearCart(User|GuestCustomer|null $user): array
    {
        $cart = $this->contextResolver->resolveCart($user);

        if ($cart) {
            $this->repository->deleteCart($cart);
        }

        if (! ($user instanceof User)) {
            $this->contextResolver->clearGuestSession();
        }

        return [];
    }

    public function removePurchasedLines(User|GuestCustomer|null $user, array $purchasedLines): array
    {
        if ($purchasedLines === []) {
            return $this->getLines($user);
        }

        return DB::transaction(function () use ($user, $purchasedLines): array {
            $cart = $this->contextResolver->resolveCart($user);
            if (! $cart || ! Cart::query()->whereKey($cart->id)->lockForUpdate()->first()) {
                return [];
            }

            $snapshots = collect($purchasedLines)->keyBy('itemId');
            $items = CartItem::query()->where('cart_id', $cart->id)
                ->whereIn('id', $snapshots->keys()->all())
                ->with([
                    'product.category',
                    'product.extraOption.items',
                    'product.weightOption.values',
                    'variant',
                    'weightOptionValue',
                    'extras.extraOptionItem',
                    'components.product',
                    'components.variant',
                ])
                ->lockForUpdate()->get();

            foreach ($items as $item) {
                $built = $this->buildCartLine($item);
                if ($built !== null && serialize($built) === serialize($snapshots->get((int) $item->id))) {
                    $this->repository->deleteCartItem($item);
                }
            }

            return $this->getLines($user);
        }, 3);
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

    private function buildCartLine(CartItem $item): ?CartLine
    {
        if (! $item->isProductLine()) {
            $handler = $this->lineTypeRegistry->get($item->line_type);
            if (! $handler) {
                $this->repository->deleteCartItem($item);

                return null;
            }

            try {
                return $handler->buildLine($item);
            } catch (Throwable $e) {
                Log::warning('Dropping cart line that its handler could not build', [
                    'cart_item_id' => $item->id,
                    'line_type' => $item->line_type,
                    'error' => $e->getMessage(),
                ]);
                $this->repository->deleteCartItem($item);

                return null;
            }
        }

        /** @var \App\Plugins\CatalogProducts\src\Models\Product $product */
        $product = $item->product;
        /** @var \App\Plugins\CatalogProducts\src\Models\ProductVariant|null $variant */
        $variant = $item->variant;
        /** @var \App\Plugins\CatalogProducts\src\Models\WeightOptionValue|null $weightValue */
        $weightValue = $item->weightOptionValue;

        $unitPrice = $this->calculator->getEffectivePrice($item);
        $finalQuantity = $this->calculator->calculateFinalQuantity($item);

        // Totals come from the calculator so the pricing rule lives in one place.
        $extrasUnitTotal = $this->calculator->calculateExtrasTotal($item);
        $subtotal = $this->calculator->calculateSubtotal($item);

        $extras = [];
        foreach ($item->extras as $extra) {
            /** @var \App\Plugins\CartEngine\src\Models\CartItemExtra $extra */
            /** @var \App\Plugins\CatalogProducts\src\Models\ExtraOptionItem $extraItem */
            $extraItem = $extra->extraOptionItem;

            $extras[] = new CartLineExtra(
                extraOptionItemId: (int) $extraItem->id,
                name: (string) $extraItem->name,
                nameAr: $extraItem->name_ar !== null ? (string) $extraItem->name_ar : null,
                unitPrice: (float) $extraItem->price,
                quantity: (int) $extra->quantity,
            );
        }

        $weightLabel = null;
        if ($weightValue !== null) {
            $weightLabel = $weightValue->label ?? (isset($weightValue->value) ? (string) $weightValue->value : null);
        }

        return new CartLine(
            itemId: (int) $item->id,
            productId: (int) $item->product_id,
            categoryId: $product->category_id !== null ? (int) $product->category_id : null,
            variantId: $item->variant_id !== null ? (int) $item->variant_id : null,
            weightOptionValueId: $item->weight_option_value_id !== null ? (int) $item->weight_option_value_id : null,
            weightMultiplier: (int) $item->weight_multiplier,
            quantity: $finalQuantity,
            unitPrice: $unitPrice,
            extrasUnitTotal: $extrasUnitTotal,
            subtotal: $subtotal,
            productName: (string) $product->name,
            productNameAr: $product->name_ar !== null ? (string) $product->name_ar : null,
            variantName: $variant?->name !== null ? (string) $variant->name : null,
            variantNameAr: $variant?->name_ar !== null ? (string) $variant->name_ar : null,
            weightLabel: $weightLabel,
            notes: $item->notes,
            extras: $extras,
            productImage: $product->image,
            sellByWeight: (bool) $product->sell_by_weight,
            weightValue: $weightValue?->value !== null ? (string) $weightValue->value : null,
            type: $item->line_type ?? 'product',
            referenceId: $item->line_reference_id !== null ? (int) $item->line_reference_id : null,
            components: [],
            couponEligible: true,
        );
    }

    private function findOwnedItemOrFail(User|GuestCustomer|null $user, string|int $itemId): CartItem
    {
        $cart = $this->contextResolver->resolveCart($user);
        if (! $cart) {
            throw new CartItemNotFound();
        }

        try {
            $cartItem = $this->repository->findCartItemOrFail((int) $itemId);
        } catch (ModelNotFoundException) {
            throw new CartItemNotFound();
        }

        if ($cartItem->cart_id !== $cart->id) {
            throw new CartItemNotFound();
        }

        return $cartItem;
    }
}
