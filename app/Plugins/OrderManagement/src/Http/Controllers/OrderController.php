<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Plugins\CartEngine\src\Services\CartService;
use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\LocationsDelivery\src\Models\Governorate;
use App\Plugins\OrderManagement\src\DTOs\PlaceOrderData;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\OrderManagement\src\Services\Contracts\PlaceOrderServiceInterface;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function __construct(
        protected readonly PlaceOrderServiceInterface $placeOrderService,
        protected readonly CartService $cartService
    ) {}

    /**
     * Place a new order
     */
    public function placeOrder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'branch_id' => 'required|integer|exists:branches,id',
            'payment_method' => 'required|string|in:card,wallet,cod,kiosk,bank_transfer',
            'address_id' => 'nullable|integer|exists:addresses,id',
            'coupon_id' => 'nullable|integer|exists:coupons,id',
            'note' => 'nullable|string|max:1000',
            'type' => 'required|in:web_delivery,web_takeaway,pos',

            // Guest user data
            'guest_data' => 'nullable|array',
            'guest_data.name' => 'required_with:guest_data|string|max:255',
            'guest_data.phone' => 'required_with:guest_data|string|max:20',
            'guest_data.phone_country_code' => 'nullable|string|max:5',
            'guest_data.email' => 'nullable|email|max:255',
            'guest_data.street' => 'nullable|string|max:255',
            'guest_data.building' => 'nullable|string|max:255',
            'guest_data.floor' => 'nullable|string|max:255',
            'guest_data.apartment' => 'nullable|string|max:255',
            'guest_data.city' => 'nullable|string|max:255',
            'guest_data.area_id' => 'nullable|integer|exists:areas,id',

            // Billing data
            'billing_data' => 'nullable|array',
            'billing_data.first_name' => 'nullable|string|max:255',
            'billing_data.last_name' => 'nullable|string|max:255',
            'billing_data.email' => 'nullable|email|max:255',
            'billing_data.phone_number' => 'nullable|string|max:20',
            'billing_data.apartment' => 'nullable|string|max:255',
            'billing_data.floor' => 'nullable|string|max:255',
            'billing_data.street' => 'nullable|string|max:255',
            'billing_data.building' => 'nullable|string|max:255',
            'billing_data.city' => 'nullable|string|max:255',
            'billing_data.country' => 'nullable|string|max:2',
            'billing_data.postal_code' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();

        // If not authenticated, try finding/creating guest user dynamically
        if (! $user) {
            $guestData = $request->input('guest_data');
            if (! $guestData) {
                return response()->json([
                    'success' => false,
                    'errors' => ['guest_data' => ['Guest information is required']],
                ], 422);
            }

            if (app()->bound('guest.user.service')) {
                $user = app('guest.user.service')->findOrCreate($guestData);
            } elseif (class_exists(\App\Plugins\GuestCheckout\src\Services\GuestUserService::class)) {
                $user = app(\App\Plugins\GuestCheckout\src\Services\GuestUserService::class)->findOrCreate($guestData);
            } else {
                return response()->json([
                    'success' => false,
                    'errors' => ['guest_data' => ['Guest ordering is currently unavailable']],
                ], 422);
            }
        }

        try {
            $paymentMethod = PaymentMethod::from($request->input('payment_method'));

            $orderData = new PlaceOrderData(
                user: $user,
                branchId: (int) $request->input('branch_id'),
                paymentMethod: $paymentMethod,
                addressId: $request->filled('address_id') ? (int) $request->input('address_id') : null,
                couponId: $request->filled('coupon_id') ? (int) $request->input('coupon_id') : null,
                note: $request->input('note'),
                type: (string) $request->input('type'),
                billingData: (array) $request->input('billing_data', [])
            );

            $result = $this->placeOrderService->placeOrder($orderData);

            if (! $result->isSuccessful()) {
                return response()->json([
                    'success' => false,
                    'errors' => ['order' => [$result->errorMessage]],
                ], 400);
            }

            $order = $result->order;

            // Handle COD response
            if ($paymentMethod === PaymentMethod::COD) {
                if ($user && ! ($user instanceof User)) {
                    if (Route::has('orders.track.page')) {
                        return response()->json([
                            'success' => true,
                            'redirect_type' => 'internal',
                            'redirect_url' => route('orders.track.page', [
                                'order_number' => $order->order_number,
                                'phone' => $user->phone ?? null,
                            ]),
                            'order_id' => $order->id,
                            'order_number' => $order->order_number,
                        ]);
                    }
                }

                return response()->json([
                    'success' => true,
                    'redirect_type' => 'internal',
                    'redirect_url' => route('orders.show', ['orderId' => $order->id]),
                    'order_id' => $order->id,
                ]);
            }

            // Online payment methods
            if ($result->checkoutUrl) {
                return response()->json([
                    'success' => true,
                    'redirect_type' => 'external',
                    'redirect_url' => $result->checkoutUrl,
                    'order_id' => $order->id,
                ]);
            }

            return response()->json([
                'success' => false,
                'errors' => ['payment' => ['Payment checkout URL not available']],
            ], 400);
        } catch (Exception $e) {
            Log::error('Place order exception', [
                'user_id' => $user?->id ?? 'guest',
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'errors' => ['error' => ['Failed to place order']],
            ], 500);
        }
    }

    /**
     * Show checkout page
     */
    public function checkout(Request $request): Response
    {
        $user = Auth::user();
        $cart = $this->cartService->getCart($user);
        $addresses = $user ? $user->addresses()->with('area.governorate')->get() : [];
        $branches = Branch::where('is_active', true)->get();
        $governorates = Governorate::with([
            'areas' => function ($query) {
                $query->where('is_active', true)->orderBy('sort_order');
            },
        ])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Checkout', [
            'cart' => $cart,
            'addresses' => $addresses,
            'branches' => $branches,
            'governorates' => $governorates,
            'is_guest' => ! $user,
        ]);
    }

    /**
     * Get order details
     */
    public function show(int $orderId): Response
    {
        $user = Auth::user();

        if (! $user) {
            abort(401, 'User not authenticated');
        }

        $order = Order::with(['items.extras', 'items.product.weightOption', 'items.weightOptionValue', 'user', 'branch', 'address.area'])
            ->where('id', $orderId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        return Inertia::render('OrderShow', [
            'order' => $order,
        ]);
    }

    /**
     * Get user's orders
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();

        if (! $user) {
            abort(401, 'User not authenticated');
        }

        $orders = Order::with(['items.product.weightOption', 'items.weightOptionValue', 'branch', 'address'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return Inertia::render('MyOrders', [
            'orders' => $orders,
        ]);
    }
}
