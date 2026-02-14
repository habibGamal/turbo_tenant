<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Models\GuestUser;
use App\Services\CartService;
use App\Services\GuestUserService;
use App\Services\PlaceOrderService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

final class OrderController extends Controller
{
    public function __construct(
        private readonly PlaceOrderService $placeOrderService,
        private readonly CartService $cartService,
        private readonly GuestUserService $guestUserService
    ) {
    }

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

            // Guest user data (required if not authenticated)
            'guest_data' => 'required_without:auth|array',
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

        // If not authenticated, create/find guest user
        if (!$user) {
            $guestData = $request->input('guest_data');
            if (!$guestData) {
                return response()->json([
                    'success' => false,
                    'errors' => ['guest_data' => ['Guest information is required']],
                ], 422);
            }

            // Find or create the guest user BEFORE placing order
            $user = $this->guestUserService->findOrCreate($guestData);
        }

        try {
            $paymentMethod = PaymentMethod::from($request->input('payment_method'));

            $result = $this->placeOrderService->placeOrder(
                user: $user,
                branchId: $request->input('branch_id'),
                paymentMethod: $paymentMethod,
                addressId: $request->input('address_id'),
                couponId: $request->input('coupon_id'),
                note: $request->input('note'),
                type: $request->input('type'),
                billingData: $request->input('billing_data', [])
            );

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'errors' => ['order' => [$result['error']]],
                ], 400);
            }

            $order = $result['order'];

            // Return response for frontend to handle redirection
            if ($paymentMethod === PaymentMethod::COD) {
                // COD payment - redirect based on user type
                if ($user instanceof GuestUser) {
                    // Guest users go to track page with auto-search params
                    return response()->json([
                        'success' => true,
                        'redirect_type' => 'internal',
                        'redirect_url' => route('orders.track.page', [
                            'order_number' => $order->order_number,
                            'phone' => $user->phone,
                        ]),
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                    ]);
                }

                // Authenticated users go to order show page
                return response()->json([
                    'success' => true,
                    'redirect_type' => 'internal',
                    'redirect_url' => route('orders.show', ['orderId' => $order->id]),
                    'order_id' => $order->id,
                ]);
            }

            // Online payment methods (CARD, WALLET, KIOSK, BANK_TRANSFER)
            if (isset($result['checkout_url'])) {
                return response()->json([
                    'success' => true,
                    'redirect_type' => 'external',
                    'redirect_url' => $result['checkout_url'],
                    'order_id' => $order->id,
                ]);
            }

            // Fallback if checkout URL is missing
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
     * Handle payment callback (redirect from Paymob)
     */
    public function paymentCallback(Request $request, int $orderId): Response
    {
        try {
            $callbackData = $request->all();
            logger()->info('Payment callback data', ['data' => $callbackData]);
            $result = $this->placeOrderService->handlePaymentCallback($orderId, $callbackData);

            return Inertia::render('PaymentCallback', [
                'success' => $result['success'],
                'message' => $result['message'] ?? $result['error'] ?? 'Unknown status',
                'order' => $result['order'],
            ]);
        } catch (Exception $e) {
            Log::error('Payment callback exception', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return Inertia::render('PaymentCallback', [
                'success' => false,
                'message' => 'Failed to process payment callback',
                'order' => null,
            ]);
        }
    }

    /**
     * Show checkout page
     */
    public function checkout(Request $request): Response
    {
        $user = Auth::user();

        // Get cart data (works for both authenticated users and guests)
        $cart = $this->cartService->getCart($user);

        // Get user's addresses if authenticated
        $addresses = $user ? $user->addresses()->with('area.governorate')->get() : [];

        // Get available branches
        $branches = \App\Models\Branch::where('is_active', true)->get();

        // Get governorates with areas for address form
        $governorates = \App\Models\Governorate::with([
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
            'is_guest' => !$user,
        ]);
    }

    /**
     * Get order details
     */
    public function show(int $orderId): Response
    {
        $user = Auth::user();

        if (!$user) {
            abort(401, 'User not authenticated');
        }

        $order = \App\Models\Order::with(['items.extras', 'items.product.weightOption', 'items.weightOptionValue', 'user', 'branch', 'address.area'])
            ->where('id', $orderId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        return Inertia::render('OrderShow', [
            'order' => $order,
        ]);
    }

    /**
     * Track guest order by order number and phone
     */
    public function trackGuestOrder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_number' => 'required|string',
            'phone' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $orderNumber = $request->input('order_number');
        $phone = $request->input('phone');

        // Find guest user
        $guestUser = GuestUser::where('phone', $phone)
            ->first();

        if (!$guestUser) {
            return response()->json([
                'success' => false,
                'error' => 'No orders found for this phone number',
            ], 404);
        }

        // Find order
        $order = \App\Models\Order::with(['items.extras', 'items.product.weightOption', 'items.weightOptionValue', 'branch', 'guestUser'])
            ->where('order_number', $orderNumber)
            ->where('guest_user_id', $guestUser->id)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'error' => 'Order not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'order' => $order,
        ]);
    }

    /**
     * Get user's orders
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();

        if (!$user) {
            abort(401, 'User not authenticated');
        }

        $orders = \App\Models\Order::with(['items.product.weightOption', 'items.weightOptionValue', 'branch', 'address'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return Inertia::render('MyOrders', [
            'orders' => $orders,
        ]);
    }
}
