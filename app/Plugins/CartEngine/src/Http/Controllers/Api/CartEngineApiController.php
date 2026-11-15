<?php

declare(strict_types=1);

namespace App\Plugins\CartEngine\src\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Plugins\CartEngine\src\Http\Requests\StoreCartItemRequest;
use App\Plugins\CartEngine\src\Http\Requests\UpdateCartItemRequest;
use App\Plugins\CartEngine\src\Http\Resources\CartResource;
use App\Plugins\CartEngine\src\Services\Contracts\CartServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

final class CartEngineApiController extends Controller
{
    public function __construct(private readonly CartServiceInterface $cartService) {}

    /**
     * Get current cart data (guest or authenticated user).
     */
    public function index(Request $request): JsonResponse
    {
        $lines = $this->cartService->getLines($request->user());

        return ApiResponse::success(new CartResource($lines));
    }

    /**
     * Add an item to the cart.
     */
    public function store(StoreCartItemRequest $request): JsonResponse
    {
        $type = $request->input('type');

        if ($type !== null && $type !== 'product') {
            $validated = $request->validated();
            $payload = Arr::except($request->all(), ['type', 'quantity', 'notes']);

            $lines = $this->cartService->addCompositeItem(
                $request->user(),
                (string) $type,
                $payload,
                (int) $validated['quantity'],
                isset($validated['notes']) ? (string) $validated['notes'] : null,
            );

            return ApiResponse::success(new CartResource($lines), 'Item added to cart successfully', 201);
        }

        $validated = $request->validated();

        $lines = $this->cartService->addItem(
            $request->user(),
            (int) $validated['product_id'],
            isset($validated['variant_id']) ? (int) $validated['variant_id'] : null,
            (string) $validated['quantity'],
            $validated['extras'] ?? [],
            isset($validated['weight_option_value_id']) ? (int) $validated['weight_option_value_id'] : null,
            isset($validated['weight_multiplier']) ? (int) $validated['weight_multiplier'] : 1
        );

        return ApiResponse::success(new CartResource($lines), 'Item added to cart successfully', 201);
    }

    /**
     * Update an item's quantity or weight modifiers in the cart.
     */
    public function update(UpdateCartItemRequest $request, string $itemId): JsonResponse
    {
        $validated = $request->validated();
        $lines = $this->cartService->updateItem(
            $request->user(),
            $itemId,
            isset($validated['quantity']) ? (string) $validated['quantity'] : null,
            isset($validated['weight_multiplier']) ? (int) $validated['weight_multiplier'] : null,
            isset($validated['weight_option_value_id']) ? (int) $validated['weight_option_value_id'] : null
        );

        return ApiResponse::success(new CartResource($lines), 'Cart item updated successfully');
    }

    /**
     * Remove an item from the cart.
     */
    public function destroy(Request $request, string $itemId): JsonResponse
    {
        $lines = $this->cartService->removeItem(
            $request->user(),
            $itemId
        );

        return ApiResponse::success(new CartResource($lines), 'Item removed from cart successfully');
    }

    /**
     * Clear all items from the cart.
     */
    public function clear(Request $request): JsonResponse
    {
        $lines = $this->cartService->clearCart($request->user());

        return ApiResponse::success(new CartResource($lines), 'Cart cleared successfully');
    }

    /**
     * Synchronize a guest cart into an authenticated user's cart.
     */
    public function sync(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401, 'User must be authenticated');
        }

        $this->cartService->syncGuestCartToUser($user);
        $lines = $this->cartService->getLines($user);

        return ApiResponse::success(new CartResource($lines), 'Guest cart synced successfully');
    }
}
