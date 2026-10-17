<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Services;

use App\Plugins\CartEngine\src\Services\CartService;
use App\Plugins\OrderManagement\src\DTOs\OrderPlacementResult;
use App\Plugins\OrderManagement\src\DTOs\PlaceOrderData;
use App\Plugins\OrderManagement\src\Exceptions\OrderValidationException;
use App\Plugins\OrderManagement\src\Pipelines\Context\OrderValidationContext;
use App\Plugins\OrderManagement\src\Pipelines\Context\PricingCalculationContext;
use App\Plugins\OrderManagement\src\Pipelines\OrderValidationPipeline;
use App\Plugins\OrderManagement\src\Pipelines\PricingCalculationPipeline;
use App\Plugins\OrderManagement\src\Services\Contracts\OrderPaymentDispatcherInterface;
use App\Plugins\OrderManagement\src\Services\Contracts\OrderRepositoryInterface;
use App\Plugins\OrderManagement\src\Services\Contracts\PlaceOrderServiceInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

class PlaceOrderService implements PlaceOrderServiceInterface
{
    public function __construct(
        private CartService $cartService,
        private OrderValidationPipeline $validationPipeline,
        private PricingCalculationPipeline $pricingPipeline,
        private OrderRepositoryInterface $orderRepository,
        private OrderPaymentDispatcherInterface $paymentDispatcher
    ) {}

    public function placeOrder(PlaceOrderData $data): OrderPlacementResult
    {
        $cartUser = $data->isGuest() ? null : $data->user;
        $cart = $this->cartService->getCart($cartUser);

        try {
            $validationContext = new OrderValidationContext($data, $cart);
            $validationContext = $this->validationPipeline->process($validationContext);

            $pricingContext = new PricingCalculationContext(
                data: $data,
                cartItems: $cart['items'] ?? [],
                attributes: $validationContext->attributes()
            );
            $pricingContext = $this->pricingPipeline->process($pricingContext);
            $totals = $pricingContext->toOrderTotals();

            $order = $this->orderRepository->createOrderWithItems(
                data: $data,
                cartItems: $cart['items'] ?? [],
                totals: $totals,
                validationContext: $validationContext
            );

            if (! $data->paymentMethod->requiresOnlinePayment()) {
                $finalizedOrder = $this->orderRepository->finalizeOfflineOrder($order);

                return OrderPlacementResult::success(
                    order: $finalizedOrder,
                    requiresPayment: false
                );
            }

            return $this->paymentDispatcher->dispatchPayment($order, $data);
        } catch (OrderValidationException $e) {
            return OrderPlacementResult::failure($e->getMessage());
        } catch (Throwable $e) {
            Log::error('Failed to place order', [
                'user_id' => $data->isGuest() ? 'guest' : ($data->user->id ?? 'unknown'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return OrderPlacementResult::failure('Failed to place order: '.$e->getMessage());
        }
    }
}
