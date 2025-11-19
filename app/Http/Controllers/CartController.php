<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreCartItemRequest;
use App\Http\Requests\UpdateCartItemRequest;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class CartController extends Controller
{
    public function __construct(private readonly CartService $cartService) {}

    public function index(Request $request): Response
    {
        $cart = $this->cartService->getCart($request->user());

        return Inertia::render('Cart', [
            'cart' => $cart,
        ]);
    }

    public function store(StoreCartItemRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $cart = $this->cartService->addItem(
            $request->user(),
            $validated['product_id'],
            $validated['variant_id'] ?? null,
            $validated['quantity'],
            $validated['extras'] ?? []
        );

        return response()->json([
            'message' => 'Item added to cart successfully',
            'cart' => $cart,
        ]);
    }

    public function update(UpdateCartItemRequest $request, string $itemId): Response
    {
        $validated = $request->validated();

        $cart = $this->cartService->updateItem(
            $request->user(),
            (int) $itemId,
            $validated['quantity']
        );

        return Inertia::render('Cart', [
            'cart' => $cart,
        ]);
    }

    public function destroy(Request $request, string $itemId): Response
    {
        $cart = $this->cartService->removeItem(
            $request->user(),
            (int) $itemId
        );

        return Inertia::render('Cart', [
            'cart' => $cart,
        ]);
    }

    public function clear(Request $request): Response
    {
        $cart = $this->cartService->clearCart($request->user());

        return Inertia::render('Cart', [
            'cart' => $cart,
        ]);
    }

    public function sync(Request $request): JsonResponse
    {
        if (! $request->user()) {
            return response()->json([
                'message' => 'User must be authenticated',
            ], 401);
        }

        $this->cartService->syncGuestCartToUser($request->user());

        $cart = $this->cartService->getCart($request->user());

        return response()->json([
            'message' => 'Guest cart synced successfully',
            'cart' => $cart,
        ]);
    }
}
