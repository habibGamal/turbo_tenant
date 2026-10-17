<?php

declare(strict_types=1);

namespace App\Plugins\PaymentProcessing\src\Pipelines\Pipes;

use App\Enums\PaymentMethod;
use App\Enums\SettingKey;
use App\Plugins\OrderManagement\src\Pipelines\Context\PricingCalculationContext;
use App\Plugins\OrderManagement\src\Pipelines\Contracts\PricingCalculationPipeInterface;
use App\Plugins\OrderManagement\src\Pipelines\DTOs\PricingAdjustment;
use App\Plugins\StoreSettings\src\Services\SettingService;
use Closure;

final readonly class ApplyCodFeePipe implements PricingCalculationPipeInterface
{
    public function __construct(private SettingService $settingService) {}

    public function handle(PricingCalculationContext $context, Closure $next): mixed
    {
        if ($context->data->paymentMethod === PaymentMethod::COD) {
            $codFee = (float) $this->settingService->get(SettingKey::COD_FEE, 0);
            if ($codFee > 0) {
                $context->addAdjustment(new PricingAdjustment(
                    identifier: 'fee:cod',
                    label: 'Cash on Delivery Fee',
                    amount: $codFee,
                    type: PricingAdjustment::TYPE_FEE
                ));
            }
        }

        return $next($context);
    }
}
