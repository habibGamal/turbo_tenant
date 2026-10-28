<?php

declare(strict_types=1);

namespace App\Plugins\CartEngine\src\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Plugins\CartEngine\src\Http\Requests\StoreCartItemRequest;
use App\Plugins\CartEngine\src\Http\Requests\UpdateCartItemRequest;
use App\Plugins\CartEngine\src\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CartEngineApiController extends Controller
{
    public function __construct(private readonly CartService $cartService) {}

    /**
     * Get current cart data (guest or authenticated user).
     */
    public function index(Request $request): JsonResponse
    {
        $cart = $this->cartService->getCart($request->user());

        return response()->json([
            'status' => 'success',
            'cart' => $cart,
        ]);
    }

    /**
     * Add an item to the cart.
     */
    public function store(StoreCartItemRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $cart = $this->cartService->addItem(
            $request->user(),
            (int) $validated['product_id'],
            isset($validated['variant_id']) ? (int) $validated['variant_id'] : null,
            (string) $validated['quantity'],
            $validated['extras'] ?? [],
            isset($validated['weight_option_value_id']) ? (int) $validated['weight_option_value_id'] : null,
            isset($validated['weight_multiplier']) ? (int) $validated['weight_multiplier'] : 1
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Item added to cart successfully',
            'cart' => $cart,
        ], 201);
    }

    /**
     * Update an item's quantity or weight modifiers in the cart.
     */
    public function update(UpdateCartItemRequest $request, string $itemId): JsonResponse
    {
        $validated = $request->validated();
        $cart = $this->cartService->updateItem(
            $request->user(),
            $itemId,
            isset($validated['quantity']) ? (string) $validated['quantity'] : null,
            isset($validated['weight_multiplier']) ? (int) $validated['weight_multiplier'] : null,
            isset($validated['weight_option_value_id']) ? (int) $validated['weight_option_value_id'] : null
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Cart item updated successfully',
            'cart' => $cart,
        ]);
    }

    /**
     * Remove an item from the cart.
     */
    public function destroy(Request $request, string $itemId): JsonResponse
    {
        $cart = $this->cartService->removeItem(
            $request->user(),
            $itemId
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Item removed from cart successfully',
            'cart' => $cart,
        ]);
    }

    /**
     * Clear all items from the cart.
     */
    public function clear(Request $request): JsonResponse
    {
        $cart = $this->cartService->clearCart($request->user());

        return response()->json([
            'status' => 'success',
            'message' => 'Cart cleared successfully',
            'cart' => $cart,
        ]);
    }

    /**
     * Synchronize a guest cart into an authenticated user's cart.
     */
    public function sync(Request $request): JsonResponse
    {
        if (! $request->user()) {
            return response()->json([
                'status' => 'error',
                'message' => 'User must be authenticated',
            ], 401);
        }

        $this->cartService->syncGuestCartToUser($request->user());
        $cart = $this->cartService->getCart($request->user());

        return response()->json([
            'status' => 'success',
            'message' => 'Guest cart synced successfully',
            'cart' => $cart,
        ]);
    }
}
