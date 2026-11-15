<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement;

use App\Core\Plugins\AbstractTenantPlugin;
use App\Models\Tenant;
use App\Plugins\OrderManagement\src\Contracts\OrderPipelineRegistrar;
use App\Plugins\OrderManagement\src\Pipelines\Pipes\Pricing\CalculateDeliveryFeePipe;
use App\Plugins\OrderManagement\src\Pipelines\Pipes\Pricing\CalculateServiceFeePipe;
use App\Plugins\OrderManagement\src\Pipelines\Pipes\Pricing\CalculateSubtotalPipe;
use App\Plugins\OrderManagement\src\Pipelines\Pipes\Pricing\CalculateTaxPipe;
use App\Plugins\OrderManagement\src\Pipelines\Pipes\Validation\ValidateBranchActivePipe;
use App\Plugins\OrderManagement\src\Pipelines\Pipes\Validation\ValidateCartNotEmptyPipe;
use App\Plugins\OrderManagement\src\Pipelines\Pipes\Validation\ValidateMinimumOrderPipe;
use App\Plugins\OrderManagement\src\Pipelines\Pipes\Validation\ValidateProductsActivePipe;
use App\Plugins\OrderManagement\src\Pipelines\Pipes\Validation\ValidateStoreOpenPipe;
use App\Plugins\OrderManagement\src\Pipelines\Pipes\Validation\ValidateUserAuthenticatedPipe;

final class OrderManagementPlugin extends AbstractTenantPlugin
{
    public function bootForTenant(Tenant $tenant): void
    {
        $registry = app(OrderPipelineRegistrar::class);

        // Register core validation pipes
        $registry->registerValidationPipe(ValidateCartNotEmptyPipe::class, 100);
        $registry->registerValidationPipe(ValidateBranchActivePipe::class, 150);
        $registry->registerValidationPipe(ValidateProductsActivePipe::class, 200);
        $registry->registerValidationPipe(ValidateMinimumOrderPipe::class, 250);
        $registry->registerValidationPipe(ValidateStoreOpenPipe::class, 300);
        $registry->registerValidationPipe(ValidateUserAuthenticatedPipe::class, 400);

        // Register core pricing pipes
        $registry->registerPricingPipe(CalculateSubtotalPipe::class, 100);
        $registry->registerPricingPipe(CalculateTaxPipe::class, 300);
        $registry->registerPricingPipe(CalculateServiceFeePipe::class, 400);
        $registry->registerPricingPipe(CalculateDeliveryFeePipe::class, 500);
    }
}
