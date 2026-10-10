<?php

declare(strict_types=1);

namespace App\Services\Cart\Repositories;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CartItemExtra;
use App\Services\Cart\Contracts\CartRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class CartRepository implements CartRepositoryInterface
{
    private const CART_RELATIONS = [
        'items.product.category',
        'items.product.extraOption.items',
        'items.product.weightOption.values',
        'items.variant',
        'items.weightOptionValue',
        'items.extras.extraOptionItem',
    ];

    public function findCartWithRelations(string $column, mixed $value): ?Cart
    {
        return Cart::with(self::CART_RELATIONS)
            ->where($column, $value)
            ->first();
    }

    public function countCartItems(?Cart $cart): int
    {
        if (! $cart) {
            return 0;
        }

        return (int) $cart->items()->count();
    }

    public function findCartItemOrFail(int $itemId): CartItem
    {
        return CartItem::with('cart.user')->findOrFail($itemId);
    }

    public function createCartItem(
        Cart $cart,
        int $productId,
        ?int $variantId,
        string $quantity,
        array $extras = [],
        ?int $weightOptionValueId = null,
        int $weightMultiplier = 1
    ): CartItem {
        return DB::transaction(function () use ($cart, $productId, $variantId, $quantity, $extras, $weightOptionValueId, $weightMultiplier) {
            $cartItem = CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $productId,
                'variant_id' => $variantId,
                'weight_option_value_id' => $weightOptionValueId,
                'quantity' => $quantity,
                'weight_multiplier' => $weightMultiplier,
            ]);

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

            return $cartItem;
        });
    }

    public function updateCartItem(CartItem $item, array $attributes): CartItem
    {
        if (! empty($attributes)) {
            $item->update($attributes);
            $item->cart->update(['last_activity_at' => now()]);
        }

        return $item;
    }

    public function deleteCartItem(CartItem $item): void
    {
        $item->delete();
    }

    public function deleteCart(Cart $cart): void
    {
        $cart->items()->delete();
        $cart->delete();
    }

    public function touchCart(Cart $cart): void
    {
        $cart->update(['last_activity_at' => now()]);
    }

    public function migrateCart(Cart $fromCart, Cart $toCart): void
    {
        DB::transaction(function () use ($fromCart, $toCart) {
            foreach ($fromCart->items as $fromItem) {
                $newItem = CartItem::create([
                    'cart_id' => $toCart->id,
                    'product_id' => $fromItem->product_id,
                    'variant_id' => $fromItem->variant_id,
                    'weight_option_value_id' => $fromItem->weight_option_value_id,
                    'quantity' => $fromItem->quantity,
                    'weight_multiplier' => $fromItem->weight_multiplier,
                ]);

                foreach ($fromItem->extras as $fromExtra) {
                    CartItemExtra::create([
                        'cart_item_id' => $newItem->id,
                        'extra_option_item_id' => $fromExtra->extra_option_item_id,
                        'quantity' => $fromExtra->quantity,
                    ]);
                }
            }

            $toCart->update(['last_activity_at' => now()]);

            $fromCart->items()->delete();
            $fromCart->delete();
        });
    }
}
