<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Payment;

use App\Plugins\PaymentProcessing\src\Contracts\PaymentGatewayInterface;
use App\Plugins\PaymentProcessing\src\DTOs\PaymentProcessingConfig;
use App\Plugins\PaymentProcessing\src\Services\GatewayRegistry;
use App\Plugins\PaymentProcessing\src\Services\KashierService;
use App\Plugins\PaymentProcessing\src\Services\PaymobService;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

final class GatewayRegistryTest extends TestCase
{
    private Container $container;

    private PaymentProcessingConfig $config;

    private GatewayRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = $this->app;
        $this->config = new PaymentProcessingConfig(
            activeGateway: 'paymob',
            paymobBaseUrl: 'https://accept.paymob.com',
            paymobSecretKey: 'secret',
            paymobPublicKey: 'public',
            paymobIntegrationIds: [123],
            paymobHmacSecret: 'hmac',
            paymobCurrency: 'EGP',
            kashierMerchantId: 'mid',
            kashierApiKey: 'key',
            kashierSecretKey: 'sec',
            kashierMode: 'test',
            kashierCurrency: 'EGP',
            kashierAllowedMethods: 'card'
        );

        $this->registry = new GatewayRegistry($this->container, $this->config);
    }

    public function test_registers_and_checks_gateway_availability(): void
    {
        $this->assertFalse($this->registry->has('paymob'));
        $this->assertSame([], $this->registry->available());

        $this->registry->register('paymob', 'Paymob', PaymobService::class);
        $this->registry->register('kashier', 'Kashier', KashierService::class);

        $this->assertTrue($this->registry->has('paymob'));
        $this->assertTrue($this->registry->has('kashier'));
        $this->assertFalse($this->registry->has('stripe'));

        $this->assertSame([
            'paymob' => 'Paymob',
            'kashier' => 'Kashier',
        ], $this->registry->available());
    }

    public function test_resolves_gateway_lazily_via_container(): void
    {
        $this->registry->register('paymob', 'Paymob', PaymobService::class);

        $mockPaymob = Mockery::mock(PaymentGatewayInterface::class);
        $this->app->instance(PaymobService::class, $mockPaymob);

        $resolved = $this->registry->get('paymob');

        $this->assertSame($mockPaymob, $resolved);
    }

    public function test_unknown_gateway_throws_invalid_argument_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown payment gateway: unknown_gateway');

        $this->registry->get('unknown_gateway');
    }

    public function test_resolves_active_gateway_from_config(): void
    {
        $this->registry->register('paymob', 'Paymob', PaymobService::class);
        $this->registry->register('kashier', 'Kashier', KashierService::class);

        $mockPaymob = Mockery::mock(PaymentGatewayInterface::class);
        $this->app->instance(PaymobService::class, $mockPaymob);

        $active = $this->registry->active();

        $this->assertSame($mockPaymob, $active);
    }

    public function test_custom_gateway_can_be_registered_and_resolved(): void
    {
        $mockCustomGateway = Mockery::mock(PaymentGatewayInterface::class);
        $customClass = get_class($mockCustomGateway);

        $this->app->instance($customClass, $mockCustomGateway);

        $this->registry->register('custom', 'Custom Gateway', $customClass);

        $this->assertTrue($this->registry->has('custom'));
        $this->assertSame($mockCustomGateway, $this->registry->get('custom'));
        $this->assertArrayHasKey('custom', $this->registry->available());
    }
}
