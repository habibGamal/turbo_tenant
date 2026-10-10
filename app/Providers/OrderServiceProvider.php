<?php

declare(strict_types=1);

namespace App\Providers;

use App\Interfaces\PaymentGatewayInterface;
use App\Services\Order\Contracts\OrderPaymentDispatcherInterface;
use App\Services\Order\Contracts\OrderPricingCalculatorInterface;
use App\Services\Order\Contracts\OrderRepositoryInterface;
use App\Services\Order\Contracts\OrderValidatorInterface;
use App\Services\Order\Contracts\PlaceOrderServiceInterface;
use App\Services\Order\Payments\OrderPaymentDispatcher;
use App\Services\Order\Pricing\OrderPricingCalculator;
use App\Services\Order\Repositories\OrderRepository;
use App\Services\Order\Validators\OrderValidator;
use App\Services\Payment\Contracts\PaymentCallbackServiceInterface;
use App\Services\Payment\Contracts\PaymentWebhookServiceInterface;
use App\Services\Payment\PaymentCallbackService;
use App\Services\Payment\PaymentWebhookService;
use App\Services\PaymentGatewayFactory;
use Illuminate\Support\ServiceProvider;

final class OrderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentGatewayInterface::class, function ($app): PaymentGatewayInterface {
            return $app->make(PaymentGatewayFactory::class)->getActiveGateway();
        });

        $this->app->bind(OrderValidatorInterface::class, OrderValidator::class);
        $this->app->bind(OrderPricingCalculatorInterface::class, OrderPricingCalculator::class);
        $this->app->bind(OrderRepositoryInterface::class, OrderRepository::class);
        $this->app->bind(OrderPaymentDispatcherInterface::class, OrderPaymentDispatcher::class);
        $this->app->bind(PlaceOrderServiceInterface::class, \App\Services\PlaceOrderService::class);

        $this->app->bind(PaymentCallbackServiceInterface::class, PaymentCallbackService::class);
        $this->app->bind(PaymentWebhookServiceInterface::class, PaymentWebhookService::class);
    }
}
