<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Pipelines\Pipes\Validation;

use App\Exceptions\OrderValidationException;
use App\Plugins\OrderManagement\src\Pipelines\Context\OrderValidationContext;
use App\Plugins\OrderManagement\src\Pipelines\Contracts\OrderValidationPipeInterface;
use Closure;

final class ValidateUserAuthenticatedPipe implements OrderValidationPipeInterface
{
    public function handle(OrderValidationContext $context, Closure $next): mixed
    {
        if (! $context->isAuthorized()) {
            throw OrderValidationException::withMessage('Guest checkout is not permitted for this store. Please log in.');
        }

        return $next($context);
    }
}
