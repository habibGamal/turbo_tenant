<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Pipelines\Pipes\Validation;

use App\Exceptions\OrderValidationException;
use App\Plugins\OrderManagement\src\Pipelines\Context\OrderValidationContext;
use App\Plugins\OrderManagement\src\Pipelines\Contracts\OrderValidationPipeInterface;
use Closure;

final class ValidateCartNotEmptyPipe implements OrderValidationPipeInterface
{
    public function handle(OrderValidationContext $context, Closure $next): mixed
    {
        if (empty($context->getCartItems())) {
            throw OrderValidationException::withMessage('Cart is empty');
        }

        return $next($context);
    }
}
