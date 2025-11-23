<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CartItemExtra;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

final class CartService
{
    public function getCart(?User $user): array
    {
        if ($user) {
            return $this->getCartFromDatabase($user);
        }

        return $this->getCartFromSession();
    }

    public function addItem(
        ?User $user,
        int $productId,
        ?int $variantId,
        string $quantity,
        array $extras = [],
        ?int $weightOptionValueId = null,
        int $weightMultiplier = 1
    ): array {
        if ($user) {
            return $this->addItemToDatabase($user, $productId, $variantId, $quantity, $extras, $weightOptionValueId, $weightMultiplier);
        }

        return $this->addItemToSession($productId, $variantId, $quantity, $extras, $weightOptionValueId, $weightMultiplier);
    }

    public function updateItem(
        ?User $user,
        string|int $itemId,
        ?string $quantity = null,
        ?int $weightMultiplier = null,
        ?int $weightOptionValueId = null
    ): array {
        if ($user) {
            return $this->updateItemInDatabase($itemId, $quantity, $weightMultiplier, $weightOptionValueId);
        }

        return $this->updateItemInSession($itemId, $quantity, $weightMultiplier, $weightOptionValueId);
    }

    public function removeItem(?User $user, string|int $itemId): array
    {
        if ($user) {
            return $this->removeItemFromDatabase((int) $itemId);
        }

        return $this->removeItemFromSession($itemId);
    }

    public function clearCart(?User $user): array
    {
        if ($user) {
            return $this->clearCartFromDatabase($user);
        }

        return $this->clearCartFromSession();
    }

    public function syncGuestCartToUser(User $user): void
    {
        $guestCartIdentifier = $this->getGuestCartIdentifier();
        $guestCart = Cart::where('session_id', $guestCartIdentifier)->first();

        if (! $guestCart || $guestCart->items->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($user, $guestCart) {
            $userCart = Cart::firstOrCreate(
                ['user_id' => $user->id],
                ['last_activity_at' => now()]
            );

            foreach ($guestCart->items as $guestItem) {
                $cartItem = CartItem::create([
                    'cart_id' => $userCart->id,
                    'product_id' => $guestItem->product_id,
                    'variant_id' => $guestItem->variant_id,
                    'weight_option_value_id' => $guestItem->weight_option_value_id,
                    'quantity' => $guestItem->quantity,
                    'weight_multiplier' => $guestItem->weight_multiplier,
                ]);

                // Copy extras
                foreach ($guestItem->extras as $guestExtra) {
                    CartItemExtra::create([
                        'cart_item_id' => $cartItem->id,
                        'extra_option_item_id' => $guestExtra->extra_option_item_id,
                        'quantity' => $guestExtra->quantity,
                    ]);
                }
            }

            $userCart->update(['last_activity_at' => now()]);

            // Delete guest cart
            $guestCart->items()->delete();
            $guestCart->delete();
        });

        // Clear guest cart session
        Session::forget('guest_cart_id');
    }

    private function getGuestCartIdentifier(): string
    {
        if (! Session::has('guest_cart_id')) {
            Session::put('guest_cart_id', 'guest_'.uniqid());
        }

        return Session::get('guest_cart_id');
    }

    private function getCartFromDatabase(User $user): array
    {
        $cart = Cart::with([
            'items.product.category',
            'items.product.extraOption.items',
            'items.product.weightOption.values',
            'items.variant',
            'items.weightOptionValue',
            'items.extras.extraOptionItem',
        ])->where('user_id', $user->id)->first();

        if (! $cart) {
            return ['items' => [], 'total' => 0];
        }

        $items = $cart->items->map(function ($item) {
            return $this->formatCartItem($item);
        })->toArray();

        return [
            'items' => $items,
            'total' => $this->calculateTotal($items),
        ];
    }

    private function getCartFromSession(): array
    {
        $guestCartIdentifier = $this->getGuestCartIdentifier();

        $cart = Cart::with([
            'items.product.category',
            'items.product.extraOption.items',
            'items.product.weightOption.values',
            'items.variant',
            'items.weightOptionValue',
            'items.extras.extraOptionItem',
        ])->where('session_id', $guestCartIdentifier)->first();

        if (! $cart) {
            return ['items' => [], 'total' => 0];
        }

        $items = $cart->items->map(function ($item) {
            return $this->formatCartItem($item);
        })->toArray();

        return [
            'items' => $items,
            'total' => $this->calculateTotal($items),
        ];
    }

    private function addItemToDatabase(
        User $user,
        int $productId,
        ?int $variantId,
        string $quantity,
        array $extras,
        ?int $weightOptionValueId = null,
        int $weightMultiplier = 1
    ): array {
        DB::transaction(function () use ($user, $productId, $variantId, $quantity, $extras, $weightOptionValueId, $weightMultiplier) {
            $cart = Cart::firstOrCreate(
                ['user_id' => $user->id],
                ['last_activity_at' => now()]
            );

            // Don't merge items with different weight values - treat as separate items
            $cartItem = CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $productId,
                'variant_id' => $variantId,
                'weight_option_value_id' => $weightOptionValueId,
                'quantity' => $quantity,
                'weight_multiplier' => $weightMultiplier,
            ]);

            // Add extras
            if (! empty($extras)) {
                foreach ($extras as $extra) {
                    $extraId = is_array($extra) ? $extra['id'] : $extra;
                    $extraQuantity = is_array($extra) && isset($extra['quantity']) ? $extra['quantity'] : 1;

                    CartItemExtra::create([
                        'cart_item_id' => $cartItem->id,
                        'extra_option_item_id' => $extraId,
                        'quantity' => $extraQuantity,
                    ]);
                }
            }

            $cart->update(['last_activity_at' => now()]);
        });

        return $this->getCartFromDatabase($user);
    }

    private function addItemToSession(
        int $productId,
        ?int $variantId,
        string $quantity,
        array $extras,
        ?int $weightOptionValueId = null,
        int $weightMultiplier = 1
    ): array {
        $guestCartIdentifier = $this->getGuestCartIdentifier();

        DB::transaction(function () use ($guestCartIdentifier, $productId, $variantId, $quantity, $extras, $weightOptionValueId, $weightMultiplier) {
            $cart = Cart::firstOrCreate(
                ['session_id' => $guestCartIdentifier],
                ['last_activity_at' => now()]
            );

            // Don't merge items with different weight values - treat as separate items
            $cartItem = CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $productId,
                'variant_id' => $variantId,
                'weight_option_value_id' => $weightOptionValueId,
                'quantity' => $quantity,
                'weight_multiplier' => $weightMultiplier,
            ]);

            // Add extras
            if (! empty($extras)) {
                foreach ($extras as $extra) {
                    $extraId = is_array($extra) ? $extra['id'] : $extra;
                    $extraQuantity = is_array($extra) && isset($extra['quantity']) ? $extra['quantity'] : 1;

                    CartItemExtra::create([
                        'cart_item_id' => $cartItem->id,
                        'extra_option_item_id' => $extraId,
                        'quantity' => $extraQuantity,
                    ]);
                }
            }

            $cart->update(['last_activity_at' => now()]);
        });

        return $this->getCartFromSession();
    }

    private function updateItemInDatabase(
        string|int $itemId,
        ?string $quantity = null,
        ?int $weightMultiplier = null,
        ?int $weightOptionValueId = null
    ): array {
        $cartItem = CartItem::findOrFail((int) $itemId);

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
            $cartItem->update($updateData);
            $cartItem->cart->update(['last_activity_at' => now()]);
        }

        return $this->getCartFromDatabase($cartItem->cart->user);
    }

    private function updateItemInSession(
        string|int $itemId,
        ?string $quantity = null,
        ?int $weightMultiplier = null,
        ?int $weightOptionValueId = null
    ): array {
        $cartItem = CartItem::findOrFail((int) $itemId);

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
            $cartItem->update($updateData);
            $cartItem->cart->update(['last_activity_at' => now()]);
        }

        return $this->getCartFromSession();
    }

    private function removeItemFromDatabase(string|int $itemId): array
    {
        $cartItem = CartItem::findOrFail((int) $itemId);
        $user = $cartItem->cart->user;
        $cartItem->delete();

        return $this->getCartFromDatabase($user);
    }

    private function removeItemFromSession(string|int $itemId): array
    {
        $cartItem = CartItem::findOrFail((int) $itemId);
        $cartItem->delete();

        return $this->getCartFromSession();
    }

    private function clearCartFromDatabase(User $user): array
    {
        $cart = Cart::where('user_id', $user->id)->first();

        if ($cart) {
            $cart->items()->delete();
            $cart->delete();
        }

        return ['items' => [], 'total' => 0];
    }

    private function clearCartFromSession(): array
    {
        $guestCartIdentifier = $this->getGuestCartIdentifier();
        $cart = Cart::where('session_id', $guestCartIdentifier)->first();

        if ($cart) {
            $cart->items()->delete();
            $cart->delete();
        }

        Session::forget('guest_cart_id');

        return ['items' => [], 'total' => 0];
    }

    private function formatCartItem(CartItem $item): array
    {
        $price = $item->variant ? $item->variant->price : $item->product->base_price;

        $extrasTotal = $item->extras->sum(fn ($extra) => $extra->extraOptionItem->price * $extra->quantity);

        // For weight-based products: quantity = weight_multiplier * weight_value
        // For regular products: quantity = stored quantity
        $finalQuantity = (float) $item->quantity;
        if ($item->product->sell_by_weight && $item->weightOptionValue) {
            $finalQuantity = $item->weight_multiplier * (float) $item->weightOptionValue->value;
        }

        // Calculate subtotal based on product type
        // For weight-based products: (price * finalQuantity) + (extrasTotal * weight_multiplier)
        // For regular products: (price + extrasTotal) * finalQuantity
        $subtotal = $item->product->sell_by_weight
            ? ($price * $finalQuantity) + ($extrasTotal * $item->weight_multiplier)
            : ($price + $extrasTotal) * $finalQuantity;

        return [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'variant_id' => $item->variant_id,
            'weight_option_value_id' => $item->weight_option_value_id,
            'quantity' => $finalQuantity,
            'weight_multiplier' => $item->weight_multiplier,
            'product' => [
                'id' => $item->product->id,
                'name' => $item->product->name,
                'image' => $item->product->image,
                'base_price' => $item->product->base_price,
                'price_after_discount' => $item->product->price_after_discount,
                'sell_by_weight' => $item->product->sell_by_weight,
                'weight_option' => $item->product->weightOption ? [
                    'id' => $item->product->weightOption->id,
                    'name' => $item->product->weightOption->name,
                    'unit' => $item->product->weightOption->unit,
                    'values' => $item->product->weightOption->values->map(fn ($val) => [
                        'id' => $val->id,
                        'value' => $val->value,
                        'label' => $val->label,
                        'sort_order' => $val->sort_order,
                    ])->toArray(),
                ] : null,
            ],
            'variant' => $item->variant ? [
                'id' => $item->variant->id,
                'name' => $item->variant->name,
                'price' => $item->variant->price,
            ] : null,
            'weight_option_value' => $item->weightOptionValue ? [
                'id' => $item->weightOptionValue->id,
                'value' => $item->weightOptionValue->value,
                'label' => $item->weightOptionValue->label,
            ] : null,
            'extras' => $item->extras->map(fn ($extra) => [
                'id' => $extra->extraOptionItem->id,
                'name' => $extra->extraOptionItem->name,
                'price' => $extra->extraOptionItem->price,
                'quantity' => $extra->quantity,
            ])->toArray(),
            'price' => $price,
            'extras_total' => $extrasTotal,
            'subtotal' => $subtotal,
        ];
    }

    private function calculateTotal(array $items): float
    {
        return array_reduce($items, fn ($total, $item) => $total + ($item['subtotal'] ?? 0), 0);
    }
}
