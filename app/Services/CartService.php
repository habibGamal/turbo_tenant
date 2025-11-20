<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CartItemExtra;
use App\Models\ExtraOptionItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;

final class CartService
{
    private const COOKIE_NAME = 'cart_data';

    private const COOKIE_LIFETIME = 60 * 24 * 30; // 30 days

    public function getCart(?User $user): array
    {
        if ($user) {
            return $this->getCartFromDatabase($user);
        }

        return $this->getCartFromCookie();
    }

    public function addItem(
        ?User $user,
        int $productId,
        ?int $variantId,
        string $quantity,
        array $extras = []
    ): array {
        if ($user) {
            return $this->addItemToDatabase($user, $productId, $variantId, $quantity, $extras);
        }

        return $this->addItemToCookie($productId, $variantId, $quantity, $extras);
    }

    public function updateItem(
        ?User $user,
        string|int $itemId,
        string $quantity
    ): array {
        if ($user) {
            return $this->updateItemInDatabase($itemId, $quantity);
        }

        return $this->updateItemInCookie($itemId, $quantity);
    }

    public function removeItem(?User $user, string|int $itemId): array
    {
        if ($user) {
            return $this->removeItemFromDatabase((int) $itemId);
        }

        return $this->removeItemFromCookie($itemId);
    }

    public function clearCart(?User $user): array
    {
        if ($user) {
            return $this->clearCartFromDatabase($user);
        }

        return $this->clearCartFromCookie();
    }

    public function syncGuestCartToUser(User $user): void
    {
        $guestCart = $this->getCartFromCookie();

        if (empty($guestCart['items'])) {
            return;
        }

        DB::transaction(function () use ($user, $guestCart) {
            $cart = Cart::firstOrCreate(
                ['user_id' => $user->id],
                ['last_activity_at' => now()]
            );

            foreach ($guestCart['items'] as $item) {
                $cartItem = CartItem::updateOrCreate(
                    [
                        'cart_id' => $cart->id,
                        'product_id' => $item['product_id'],
                        'variant_id' => $item['variant_id'],
                    ],
                    [
                        'quantity' => DB::raw("quantity + {$item['quantity']}"),
                    ]
                );

                // Sync extras
                if (! empty($item['extras'])) {
                    foreach ($item['extras'] as $extraId) {
                        CartItemExtra::firstOrCreate([
                            'cart_item_id' => $cartItem->id,
                            'extra_option_item_id' => $extraId,
                        ]);
                    }
                }
            }

            $cart->update(['last_activity_at' => now()]);
        });

        // Clear guest cart cookie after sync
        Cookie::queue(Cookie::forget(self::COOKIE_NAME));
    }

    private function getCartFromDatabase(User $user): array
    {
        $cart = Cart::with([
            'items.product.category',
            'items.product.extraOption.items',
            'items.variant',
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

    private function getCartFromCookie(): array
    {
        $cartData = request()->cookie(self::COOKIE_NAME);

        if (! $cartData) {
            return ['items' => [], 'total' => 0];
        }

        $cart = json_decode($cartData, true);
        $items = $cart['items'] ?? [];

        // Load product data for each cart item
        $productIds = array_column($items, 'product_id');
        $variantIds = array_filter(array_column($items, 'variant_id'));

        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');
        $variants = ! empty($variantIds) ? ProductVariant::whereIn('id', $variantIds)->get()->keyBy('id') : collect();
        $extraOptionItems = collect();

        // Load extra option items if any extras exist
        $allExtras = array_merge(...array_column($items, 'extras'));
        if (! empty($allExtras)) {
            $extraOptionItems = ExtraOptionItem::whereIn('id', $allExtras)->get()->keyBy('id');
        }

        // Format items with full product data
        $formattedItems = array_map(function ($item) use ($products, $variants, $extraOptionItems) {
            $product = $products->get($item['product_id']);
            $variant = $item['variant_id'] ? $variants->get($item['variant_id']) : null;

            if (! $product) {
                return null; // Skip invalid products
            }

            $price = $variant ? $variant->price : $product->base_price;
            $extrasTotal = 0;
            $formattedExtras = [];

            if (! empty($item['extras'])) {
                foreach ($item['extras'] as $extraId) {
                    $extraItem = $extraOptionItems->get($extraId);
                    if ($extraItem) {
                        $formattedExtras[] = [
                            'id' => $extraItem->id,
                            'name' => $extraItem->name,
                            'price' => $extraItem->price,
                        ];
                        $extrasTotal += $extraItem->price;
                    }
                }
            }

            return [
                'id' => $item['id'],
                'product_id' => $item['product_id'],
                'variant_id' => $item['variant_id'],
                'quantity' => $item['quantity'],
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'image' => $product->image,
                    'base_price' => $product->base_price,
                    'price_after_discount' => $product->price_after_discount,
                    'sell_by_weight' => $product->sell_by_weight,
                ],
                'variant' => $variant ? [
                    'id' => $variant->id,
                    'name' => $variant->name,
                    'price' => $variant->price,
                ] : null,
                'extras' => $formattedExtras,
                'price' => $price,
                'extras_total' => $extrasTotal,
                'subtotal' => ($price + $extrasTotal) * (float) $item['quantity'],
            ];
        }, $items);

        // Filter out null items (invalid products)
        $formattedItems = array_filter($formattedItems);

        return [
            'items' => array_values($formattedItems),
            'total' => $this->calculateTotal($formattedItems),
        ];
    }

    private function addItemToDatabase(
        User $user,
        int $productId,
        ?int $variantId,
        string $quantity,
        array $extras
    ): array {
        DB::transaction(function () use ($user, $productId, $variantId, $quantity, $extras) {
            $cart = Cart::firstOrCreate(
                ['user_id' => $user->id],
                ['last_activity_at' => now()]
            );

            $cartItem = CartItem::updateOrCreate(
                [
                    'cart_id' => $cart->id,
                    'product_id' => $productId,
                    'variant_id' => $variantId,
                ],
                [
                    'quantity' => DB::raw("quantity + {$quantity}"),
                ]
            );

            // Sync extras
            if (! empty($extras)) {
                $existingExtras = $cartItem->extras()->pluck('extra_option_item_id')->toArray();
                $newExtras = array_diff($extras, $existingExtras);

                foreach ($newExtras as $extraId) {
                    CartItemExtra::create([
                        'cart_item_id' => $cartItem->id,
                        'extra_option_item_id' => $extraId,
                    ]);
                }
            }

            $cart->update(['last_activity_at' => now()]);
        });

        return $this->getCartFromDatabase($user);
    }

    private function addItemToCookie(
        int $productId,
        ?int $variantId,
        string $quantity,
        array $extras
    ): array {
        $cart = $this->getCartFromCookie();
        $items = $cart['items'];

        // Find existing item
        $found = false;
        foreach ($items as &$item) {
            if ($item['product_id'] === $productId && $item['variant_id'] === $variantId) {
                $item['quantity'] = (string) ((float) ($item['quantity']) + (float) $quantity);
                // Merge extras
                $item['extras'] = array_unique(array_merge($item['extras'], $extras));
                $found = true;
                break;
            }
        }

        if (! $found) {
            $items[] = [
                'id' => uniqid('cart_', true),
                'product_id' => $productId,
                'variant_id' => $variantId,
                'quantity' => $quantity,
                'extras' => $extras,
            ];
        }

        $cartData = ['items' => $items];
        Cookie::queue(self::COOKIE_NAME, json_encode($cartData), self::COOKIE_LIFETIME);

        return [
            'items' => $items,
            'total' => $this->calculateTotal($items),
        ];
    }

    private function updateItemInDatabase(string|int $itemId, string $quantity): array
    {
        $cartItem = CartItem::findOrFail((int) $itemId);
        $cartItem->update(['quantity' => $quantity]);
        $cartItem->cart->update(['last_activity_at' => now()]);

        return $this->getCartFromDatabase($cartItem->cart->user);
    }

    private function updateItemInCookie(string|int $itemId, string $quantity): array
    {
        $cart = $this->getCartFromCookie();
        $items = $cart['items'];
        foreach ($items as &$item) {
            if ($item['id'] === $itemId) {
                $item['quantity'] = $quantity;
                break;
            }
        }

        $cartData = ['items' => $items];

        Cookie::queue(self::COOKIE_NAME, json_encode($cartData), self::COOKIE_LIFETIME);

        return [
            'items' => $items,
            'total' => $this->calculateTotal($items),
        ];
    }

    private function removeItemFromDatabase(string|int $itemId): array
    {
        $cartItem = CartItem::findOrFail((int) $itemId);
        $user = $cartItem->cart->user;
        $cartItem->delete();

        return $this->getCartFromDatabase($user);
    }

    private function removeItemFromCookie(string|int $itemId): array
    {
        $cart = $this->getCartFromCookie();
        $items = array_filter($cart['items'], fn ($item) => $item['id'] !== $itemId);
        $items = array_values($items); // Re-index array

        $cartData = ['items' => $items];
        Cookie::queue(self::COOKIE_NAME, json_encode($cartData), self::COOKIE_LIFETIME);

        return [
            'items' => $items,
            'total' => $this->calculateTotal($items),
        ];
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

    private function clearCartFromCookie(): array
    {
        Cookie::queue(Cookie::forget(self::COOKIE_NAME));

        return ['items' => [], 'total' => 0];
    }

    private function formatCartItem(CartItem $item): array
    {
        $price = $item->variant ? $item->variant->price : $item->product->base_price;

        $extrasTotal = $item->extras->sum(fn ($extra) => $extra->extraOptionItem->price);

        return [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'variant_id' => $item->variant_id,
            'quantity' => $item->quantity,
            'product' => [
                'id' => $item->product->id,
                'name' => $item->product->name,
                'image' => $item->product->image,
                'base_price' => $item->product->base_price,
                'price_after_discount' => $item->product->price_after_discount,
                'sell_by_weight' => $item->product->sell_by_weight,
            ],
            'variant' => $item->variant ? [
                'id' => $item->variant->id,
                'name' => $item->variant->name,
                'price' => $item->variant->price,
            ] : null,
            'extras' => $item->extras->map(fn ($extra) => [
                'id' => $extra->extraOptionItem->id,
                'name' => $extra->extraOptionItem->name,
                'price' => $extra->extraOptionItem->price,
            ])->toArray(),
            'price' => $price,
            'extras_total' => $extrasTotal,
            'subtotal' => ($price + $extrasTotal) * (float) ($item->quantity),
        ];
    }

    private function calculateTotal(array $items): float
    {
        return array_reduce($items, fn ($total, $item) => $total + ($item['subtotal'] ?? 0), 0);
    }
}
