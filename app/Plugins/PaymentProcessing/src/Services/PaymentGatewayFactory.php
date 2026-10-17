<?php

declare(strict_types=1);

namespace App\Plugins\PaymentProcessing\src\Services;

use App\Plugins\PaymentProcessing\src\Contracts\PaymentGatewayInterface;
use App\Plugins\PaymentProcessing\src\DTOs\PaymentProcessingConfig;
use InvalidArgumentException;

/**
 * Factory for selecting the active payment gateway.
 * Reads the active gateway from typed configuration and returns the appropriate service.
 */
final class PaymentGatewayFactory
{
    public function __construct(
        private readonly PaymentProcessingConfig $config,
        private readonly PaymobService $paymobService,
        private readonly KashierService $kashierService
    ) {}

    /**
     * Get the currently active payment gateway.
     *
     * @throws InvalidArgumentException If the gateway ID is not recognized
     */
    public function getActiveGateway(): PaymentGatewayInterface
    {
        $gatewayId = $this->config->activeGateway ?: 'paymob';

        return $this->getGateway($gatewayId);
    }

    /**
     * Get a specific payment gateway by ID.
     *
     * @throws InvalidArgumentException If the gateway ID is not recognized
     */
    public function getGateway(string $gatewayId): PaymentGatewayInterface
    {
        return match ($gatewayId) {
            'paymob' => $this->paymobService,
            'kashier' => $this->kashierService,
            default => throw new InvalidArgumentException("Unknown payment gateway: {$gatewayId}"),
        };
    }

    /**
     * Get all available gateway IDs.
     *
     * @return array<string, string>
     */
    public function getAvailableGateways(): array
    {
        return [
            'paymob' => 'Paymob',
            'kashier' => 'Kashier',
        ];
    }

    /**
     * Check if a gateway ID is valid.
     */
    public function isValidGateway(string $gatewayId): bool
    {
        return in_array($gatewayId, array_keys($this->getAvailableGateways()), true);
    }
}
