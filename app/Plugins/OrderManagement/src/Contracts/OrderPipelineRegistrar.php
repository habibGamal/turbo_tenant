<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Contracts;

interface OrderPipelineRegistrar
{
    public function registerValidationPipe(string|callable|object $pipe, int $priority = 100): static;

    public function registerPricingPipe(string|callable|object $pipe, int $priority = 100): static;
}
