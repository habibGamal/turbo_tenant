<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Services;

use App\Plugins\CartEngine\src\Services\Contracts\CartServiceInterface;
use App\Plugins\OrderManagement\src\DTOs\OrderPlacementResult;
use App\Plugins\OrderManagement\src\DTOs\PlaceOrderData;
use App\Plugins\OrderManagement\src\Events\OrderCreated;
use App\Plugins\OrderManagement\src\Events\OrderFinalized;
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

final class PlaceOrderService implements PlaceOrderServiceInterface
{
    public function __construct(
        private CartServiceInterface $cartService,
        private OrderValidationPipeline $validationPipeline,
        private PricingCalculationPipeline $pricingPipeline,
        private OrderRepositoryInterface $orderRepository,
        private OrderPaymentDispatcherInterface $paymentDispatcher
    ) {}

    public function placeOrder(PlaceOrderData $data): OrderPlacementResult
    {
        $cartUser = $data->isGuest() ? null : $data->user;
        $lines = $this->cartService->getLines($cartUser);

        try {
            $validationContext = new OrderValidationContext($data, $lines);
            $validationContext = $this->validationPipeline->process($validationContext);

            $pricingContext = new PricingCalculationContext(
                data: $data,
                lines: $lines,
                attributes: $validationContext->attributes()
            );
            $pricingContext = $this->pricingPipeline->process($pricingContext);
            $totals = $pricingContext->toOrderTotals();

            $order = $this->orderRepository->createOrderWithItems(
                data: $data,
                lines: $lines,
                totals: $totals,
                validationContext: $validationContext
            );

            OrderCreated::dispatch($order, $validationContext);

            if (! $data->paymentMethod->requiresOnlinePayment()) {
                $finalizedOrder = $this->orderRepository->finalizeOfflineOrder($order);
                OrderFinalized::dispatch($finalizedOrder);

                return OrderPlacementResult::success(
                    order: $finalizedOrder,
                    requiresPayment: false
                );
            }

            $paymentResult = $this->paymentDispatcher->dispatchPayment($order, $data);
            if ($paymentResult->isSuccessful() && ! empty($paymentResult->checkoutUrl)) {
                $this->cartService->removePurchasedLines($cartUser, $lines);
            }

            return $paymentResult;
        } catch (OrderValidationException $e) {
            return OrderPlacementResult::failure($e->getMessage());
        } catch (Throwable $e) {
            Log::error('Failed to place order', [
                'user_id' => $data->isGuest() ? 'guest' : ($data->user->id ?? 'unknown'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return OrderPlacementResult::failure('Failed to place order');
        }
    }
}
