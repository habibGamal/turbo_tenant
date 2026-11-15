<?php

declare(strict_types=1);

namespace App\Plugins\PaymentProcessing\src\Services;

use App\Plugins\PaymentProcessing\src\Contracts\PaymentGatewayInterface;
use App\Plugins\PaymentProcessing\src\DTOs\PaymentProcessingConfig;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Registry for available payment gateways.
 * Allows gateways to be registered and lazily resolved from the container.
 */
final class GatewayRegistry
{
    /**
     * @var array<string, array{label: string, class: class-string<PaymentGatewayInterface>}>
     */
    private array $gateways = [];

    public function __construct(
        private readonly Container $container,
        private readonly PaymentProcessingConfig $config
    ) {}

    /**
     * Register a payment gateway.
     *
     * @param  class-string<PaymentGatewayInterface>  $class
     */
    public function register(string $gatewayId, string $label, string $class): void
    {
        $this->gateways[$gatewayId] = [
            'label' => $label,
            'class' => $class,
        ];
    }

    /**
     * Determine if a gateway is registered.
     */
    public function has(string $gatewayId): bool
    {
        return isset($this->gateways[$gatewayId]);
    }

    /**
     * Get a specific payment gateway by ID (resolves lazily from container).
     *
     * @throws InvalidArgumentException If the gateway ID is not recognized
     */
    public function get(string $gatewayId): PaymentGatewayInterface
    {
        if (! $this->has($gatewayId)) {
            throw new InvalidArgumentException("Unknown payment gateway: {$gatewayId}");
        }

        $gateway = $this->container->make($this->gateways[$gatewayId]['class']);

        if (! $gateway instanceof PaymentGatewayInterface) {
            throw new InvalidArgumentException("Payment gateway [{$gatewayId}] must implement ".PaymentGatewayInterface::class);
        }

        return $gateway;
    }

    /**
     * Get the currently active payment gateway.
     *
     * @throws InvalidArgumentException If the active gateway ID is not recognized
     */
    public function active(): PaymentGatewayInterface
    {
        $gatewayId = $this->config->activeGateway ?: 'paymob';

        return $this->get($gatewayId);
    }

    /**
     * Get all available gateway IDs and their human-readable labels.
     *
     * @return array<string, string>
     */
    public function available(): array
    {
        $available = [];
        foreach ($this->gateways as $id => $gateway) {
            $available[$id] = $gateway['label'];
        }

        return $available;
    }
}
