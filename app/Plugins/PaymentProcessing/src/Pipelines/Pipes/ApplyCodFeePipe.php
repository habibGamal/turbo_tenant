<?php

declare(strict_types=1);

namespace App\Plugins\PaymentProcessing\src\Pipelines\Pipes;

use App\Core\Contracts\SettingsRepository;
use App\Plugins\OrderManagement\src\Enums\PaymentMethod;
use App\Plugins\OrderManagement\src\Pipelines\Context\PricingCalculationContext;
use App\Plugins\OrderManagement\src\Pipelines\Contracts\PricingCalculationPipeInterface;
use App\Plugins\OrderManagement\src\Pipelines\DTOs\PricingAdjustment;
use App\Plugins\PaymentProcessing\src\Settings\PaymentSettingKey;
use Closure;

final readonly class ApplyCodFeePipe implements PricingCalculationPipeInterface
{
    public function __construct(private SettingsRepository $settingService) {}

    public function handle(PricingCalculationContext $context, Closure $next): mixed
    {
        if ($context->data->paymentMethod === PaymentMethod::COD) {
            $codFee = (float) $this->settingService->get(PaymentSettingKey::COD_FEE, 0);
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
