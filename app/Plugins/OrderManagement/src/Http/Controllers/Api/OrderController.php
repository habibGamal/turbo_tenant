<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Plugins\OrderManagement\src\Http\CheckoutCustomerResolver;
use App\Plugins\OrderManagement\src\Enums\OrderStatus;
use App\Plugins\OrderManagement\src\Enums\PaymentStatus;
use App\Plugins\OrderManagement\src\Http\Requests\PlaceOrderRequest;
use App\Plugins\OrderManagement\src\Http\Resources\OrderResource;
use App\Plugins\OrderManagement\src\Http\Resources\OrderSummaryResource;
use App\Plugins\OrderManagement\src\Http\Responders\OrderPlacementResponder;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\OrderManagement\src\Services\Contracts\PlaceOrderServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class OrderController extends Controller
{
    public const int DEFAULT_PER_PAGE = 10;

    public const int MIN_PER_PAGE = 1;

    public const int MAX_PER_PAGE = 50;

    public function __construct(
        protected readonly PlaceOrderServiceInterface $placeOrderService,
        private readonly CheckoutCustomerResolver $customerResolver,
        private readonly OrderPlacementResponder $responder,
    ) {}

    /**
     * Get paginated customer orders
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $rawPerPage = $request->input('per_page', self::DEFAULT_PER_PAGE);
        $perPage = is_numeric($rawPerPage) ? (int) $rawPerPage : self::DEFAULT_PER_PAGE;
        $perPage = max(self::MIN_PER_PAGE, min($perPage, self::MAX_PER_PAGE));

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
            'payment_status' => ['nullable', Rule::enum(PaymentStatus::class)],
        ]);
        $search = trim($filters['search'] ?? '');

        $orders = Order::with(['items.product', 'items.weightOptionValue', 'items.components', 'branch', 'address.area'])
            ->where('user_id', $user->id)
            ->when($search !== '', fn ($query) => $query->where('order_number', 'like', '%'.$search.'%'))
            ->when(! empty($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(! empty($filters['payment_status']), fn ($query) => $query->where('payment_status', $filters['payment_status']))
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        return ApiResponse::paginated($orders, OrderSummaryResource::class);
    }

    /**
     * Get single order details
     */
    public function show(Request $request, int $orderId): JsonResponse
    {
        $user = $request->user();

        $order = Order::with([
            'items.extras',
            'items.components',
            'items.product.weightOption',
            'items.weightOptionValue',
            'user',
            'branch',
            'address.area',
        ])
            ->where('id', $orderId)
            ->where('user_id', $user->id)
            ->first();

        if (! $order) {
            abort(404, 'Order not found');
        }

        return ApiResponse::success(new OrderResource($order));
    }

    /**
     * Place a new order
     */
    public function placeOrder(PlaceOrderRequest $request): JsonResponse
    {
        $customer = $this->customerResolver->resolve($request);

        if ($customer === null) {
            return ApiResponse::error(
                'Guest checkout is not permitted for this store. Please log in.',
                422,
                ['guest_data' => ['Guest checkout is not permitted for this store. Please log in.']]
            );
        }

        $orderData = $request->toPlaceOrderData($customer);
        $result = $this->placeOrderService->placeOrder($orderData);

        return $this->responder->respond($result, $customer);
    }
}
