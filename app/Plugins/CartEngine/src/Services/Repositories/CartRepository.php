<?php

declare(strict_types=1);

namespace App\Plugins\CartEngine\src\Services\Repositories;

use App\Plugins\CartEngine\src\DTOs\PreparedCartLine;
use App\Plugins\CartEngine\src\Models\Cart;
use App\Plugins\CartEngine\src\Models\CartItem;
use App\Plugins\CartEngine\src\Models\CartItemComponent;
use App\Plugins\CartEngine\src\Models\CartItemExtra;
use App\Plugins\CartEngine\src\Services\Contracts\CartRepositoryInterface;
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
        'items.components.product',
        'items.components.variant',
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
            if (empty($extras)) {
                $existingItem = CartItem::where('cart_id', $cart->id)
                    ->where('line_type', 'product')
                    ->where('product_id', $productId)
                    ->where('variant_id', $variantId)
                    ->where('weight_option_value_id', $weightOptionValueId)
                    ->doesntHave('extras')
                    ->first();

                if ($existingItem) {
                    $newQuantity = (string) ((float) ($existingItem->quantity) + (float) $quantity);
                    $existingItem->update([
                        'quantity' => $newQuantity,
                    ]);
                    $cart->update(['last_activity_at' => now()]);

                    return $existingItem;
                }
            }

            $cartItem = CartItem::create([
                'cart_id' => $cart->id,
                'line_type' => 'product',
                'product_id' => $productId,
                'variant_id' => $variantId,
                'weight_option_value_id' => $weightOptionValueId,
                'quantity' => $quantity,
                'weight_multiplier' => $weightMultiplier,
            ]);

            if (! empty($extras)) {
                foreach ($extras as $extra) {
                    CartItemExtra::create([
                        'cart_item_id' => $cartItem->id,
                        'extra_option_item_id' => $extra['id'],
                        'quantity' => $extra['quantity'],
                    ]);
                }
            }

            $cart->update(['last_activity_at' => now()]);

            return $cartItem;
        });
    }

    public function createCompositeCartItem(
        Cart $cart,
        string $type,
        PreparedCartLine $prepared,
        int $quantity,
        ?string $notes = null
    ): CartItem {
        return DB::transaction(function () use ($cart, $type, $prepared, $quantity, $notes) {
            $query = CartItem::query()
                ->where('cart_id', $cart->id)
                ->where('line_type', $type)
                ->where('selection_signature', $prepared->signature);

            if ($notes === null) {
                $query->whereNull('notes');
            } else {
                $query->where('notes', $notes);
            }

            $existingItem = $query->first();

            if ($existingItem) {
                $newQuantity = (string) ((int) (float) $existingItem->quantity + $quantity);
                $existingItem->update([
                    'quantity' => $newQuantity,
                ]);
                $cart->update(['last_activity_at' => now()]);

                return $existingItem;
            }

            $cartItem = CartItem::create([
                'cart_id' => $cart->id,
                'line_type' => $type,
                'line_reference_id' => $prepared->referenceId,
                'selection_signature' => $prepared->signature,
                'product_id' => null,
                'variant_id' => null,
                'weight_option_value_id' => null,
                'quantity' => (string) $quantity,
                'weight_multiplier' => 1,
                'notes' => $notes,
            ]);

            foreach ($prepared->components as $component) {
                $extrasData = array_map(function ($extra): array {
                    if (is_array($extra)) {
                        return [
                            'id' => (int) ($extra['id'] ?? $extra['extraOptionItemId'] ?? 0),
                            'quantity' => (int) ($extra['quantity'] ?? 1),
                        ];
                    }

                    return [
                        'id' => (int) ($extra->extraOptionItemId ?? $extra->id),
                        'quantity' => (int) $extra->quantity,
                    ];
                }, $component->extras);

                CartItemComponent::create([
                    'cart_item_id' => $cartItem->id,
                    'source_id' => $component->sourceId,
                    'product_id' => $component->productId,
                    'variant_id' => $component->variantId,
                    'quantity' => $component->quantity,
                    'unit_price_adjustment' => $component->unitPriceAdjustment,
                    'sort_order' => $component->sortOrder,
                    'extras' => $extrasData,
                    'notes' => $component->notes,
                ]);
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
                if ($fromItem->isProductLine()) {
                    $newItem = CartItem::create([
                        'cart_id' => $toCart->id,
                        'line_type' => 'product',
                        'product_id' => $fromItem->product_id,
                        'variant_id' => $fromItem->variant_id,
                        'weight_option_value_id' => $fromItem->weight_option_value_id,
                        'quantity' => $fromItem->quantity,
                        'weight_multiplier' => $fromItem->weight_multiplier,
                        'notes' => $fromItem->notes,
                    ]);

                    foreach ($fromItem->extras as $fromExtra) {
                        CartItemExtra::create([
                            'cart_item_id' => $newItem->id,
                            'extra_option_item_id' => $fromExtra->extra_option_item_id,
                            'quantity' => $fromExtra->quantity,
                        ]);
                    }
                } else {
                    $query = CartItem::query()
                        ->where('cart_id', $toCart->id)
                        ->where('line_type', $fromItem->line_type)
                        ->where('selection_signature', $fromItem->selection_signature);

                    if ($fromItem->notes === null) {
                        $query->whereNull('notes');
                    } else {
                        $query->where('notes', $fromItem->notes);
                    }

                    $existingItem = $query->first();

                    if ($existingItem) {
                        $newQuantity = (string) ((int) (float) $existingItem->quantity + (int) (float) $fromItem->quantity);
                        $existingItem->update([
                            'quantity' => $newQuantity,
                        ]);
                    } else {
                        $newItem = CartItem::create([
                            'cart_id' => $toCart->id,
                            'line_type' => $fromItem->line_type,
                            'line_reference_id' => $fromItem->line_reference_id,
                            'selection_signature' => $fromItem->selection_signature,
                            'product_id' => null,
                            'variant_id' => null,
                            'weight_option_value_id' => null,
                            'quantity' => $fromItem->quantity,
                            'weight_multiplier' => $fromItem->weight_multiplier ?? 1,
                            'notes' => $fromItem->notes,
                        ]);

                        foreach ($fromItem->components as $fromComponent) {
                            CartItemComponent::create([
                                'cart_item_id' => $newItem->id,
                                'source_id' => $fromComponent->source_id,
                                'product_id' => $fromComponent->product_id,
                                'variant_id' => $fromComponent->variant_id,
                                'quantity' => $fromComponent->quantity,
                                'unit_price_adjustment' => $fromComponent->unit_price_adjustment,
                                'sort_order' => $fromComponent->sort_order,
                                'extras' => $fromComponent->extras,
                                'notes' => $fromComponent->notes,
                            ]);
                        }
                    }
                }
            }

            $toCart->update(['last_activity_at' => now()]);

            $fromCart->items()->delete();
            $fromCart->delete();
        });
    }
}
