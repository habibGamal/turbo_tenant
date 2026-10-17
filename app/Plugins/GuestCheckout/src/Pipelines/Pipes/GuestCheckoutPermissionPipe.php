<?php

declare(strict_types=1);

namespace App\Plugins\GuestCheckout\src\Pipelines\Pipes;

use App\Plugins\OrderManagement\src\Pipelines\Context\OrderValidationContext;
use App\Plugins\OrderManagement\src\Pipelines\Contracts\OrderValidationPipeInterface;
use Closure;

final class GuestCheckoutPermissionPipe implements OrderValidationPipeInterface
{
    public function handle(OrderValidationContext $context, Closure $next): mixed
    {
        if ($context->data->isGuest()) {
            $context->authorize();
        }

        return $next($context);
    }
}
