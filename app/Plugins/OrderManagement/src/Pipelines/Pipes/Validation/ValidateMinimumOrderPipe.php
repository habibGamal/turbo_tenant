<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Pipelines\Pipes\Validation;

use App\Core\Contracts\SettingsRepository;
use App\Plugins\OrderManagement\src\Exceptions\OrderValidationException;
use App\Plugins\OrderManagement\src\Pipelines\Context\OrderValidationContext;
use App\Plugins\OrderManagement\src\Pipelines\Contracts\OrderValidationPipeInterface;
use App\Plugins\OrderManagement\src\Settings\OrderSettingKey;
use Closure;

final readonly class ValidateMinimumOrderPipe implements OrderValidationPipeInterface
{
    public function __construct(
        private SettingsRepository $settingService
    ) {}

    public function handle(OrderValidationContext $context, Closure $next): mixed
    {
        $minOrderAmount = (float) $this->settingService->get(OrderSettingKey::MIN_ORDER_AMOUNT, 0);

        if ($minOrderAmount > 0) {
            $subtotal = 0.0;
            foreach ($context->lines as $line) {
                $subtotal += $line->subtotal;
            }

            if ($subtotal < $minOrderAmount) {
                throw OrderValidationException::withMessage(
                    sprintf('The minimum order amount is %s.', number_format($minOrderAmount, 2))
                );
            }
        }

        return $next($context);
    }
}
