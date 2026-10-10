<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Order\OrderPlacementResult;
use App\DTOs\Order\PlaceOrderData;
use App\Exceptions\OrderValidationException;
use App\Services\Order\Contracts\OrderPaymentDispatcherInterface;
use App\Services\Order\Contracts\OrderPricingCalculatorInterface;
use App\Services\Order\Contracts\OrderRepositoryInterface;
use App\Services\Order\Contracts\OrderValidatorInterface;
use App\Services\Order\Contracts\PlaceOrderServiceInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class PlaceOrderService implements PlaceOrderServiceInterface
{
    public function __construct(
        private CartService $cartService,
        private OrderValidatorInterface $validator,
        private OrderPricingCalculatorInterface $pricingCalculator,
        private OrderRepositoryInterface $orderRepository,
        private OrderPaymentDispatcherInterface $paymentDispatcher
    ) {}

    public function placeOrder(PlaceOrderData $data): OrderPlacementResult
    {
        $cartUser = $data->isGuest() ? null : $data->user;
        $cart = $this->cartService->getCart($cartUser);

        try {
            $coupon = $this->validator->validate($data, $cart);
            $totals = $this->pricingCalculator->calculate($data, $cart['items'] ?? [], $coupon);
            $order = $this->orderRepository->createOrderWithItems($data, $cart['items'] ?? [], $totals, $coupon);

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
                'user_id' => $data->isGuest() ? 'guest' : $data->user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return OrderPlacementResult::failure('Failed to place order: '.$e->getMessage());
        }
    }
}
