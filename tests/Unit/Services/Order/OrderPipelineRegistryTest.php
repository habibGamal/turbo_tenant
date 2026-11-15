<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Order;

use App\Plugins\OrderManagement\src\Contracts\OrderPipelineRegistrar;
use App\Plugins\OrderManagement\src\Pipelines\OrderPipelineRegistry;
use Tests\TestCase;

final class OrderPipelineRegistryTest extends TestCase
{
    private OrderPipelineRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new OrderPipelineRegistry();
    }

    public function test_registers_and_sorts_validation_pipes_by_priority(): void
    {
        $this->registry->registerValidationPipe('PipeLowPriority', 500);
        $this->registry->registerValidationPipe('PipeHighPriority', 100);
        $this->registry->registerValidationPipe('PipeMidPriority', 300);

        $pipes = $this->registry->getValidationPipes();

        $this->assertSame([
            'PipeHighPriority',
            'PipeMidPriority',
            'PipeLowPriority',
        ], $pipes);
    }

    public function test_registers_and_sorts_pricing_pipes_by_priority(): void
    {
        $this->registry->registerPricingPipe('PricingLast', 400);
        $this->registry->registerPricingPipe('PricingFirst', 100);
        $this->registry->registerPricingPipe('PricingMiddle', 250);

        $pipes = $this->registry->getPricingPipes();

        $this->assertSame([
            'PricingFirst',
            'PricingMiddle',
            'PricingLast',
        ], $pipes);
    }

    public function test_same_pipe_key_overwrites_registration(): void
    {
        $this->registry->registerValidationPipe('PipeA', 500);
        $this->registry->registerValidationPipe('PipeA', 100);

        $pipes = $this->registry->getValidationPipes();

        $this->assertSame(['PipeA'], $pipes);
    }

    public function test_reset_clears_all_pipes(): void
    {
        $this->registry->registerValidationPipe('PipeA', 100);
        $this->registry->registerPricingPipe('PipeB', 100);

        $this->registry->reset();

        $this->assertEmpty($this->registry->getValidationPipes());
        $this->assertEmpty($this->registry->getPricingPipes());
    }

    public function test_implements_order_pipeline_registrar_contract(): void
    {
        $this->assertInstanceOf(OrderPipelineRegistrar::class, $this->registry);
    }

    public function test_container_resolves_registrar_interface_to_same_singleton_registry(): void
    {
        $registrar = app(OrderPipelineRegistrar::class);
        $registry = app(OrderPipelineRegistry::class);

        $this->assertInstanceOf(OrderPipelineRegistrar::class, $registrar);
        $this->assertSame($registry, $registrar);
    }
}
