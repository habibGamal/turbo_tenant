<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Pipelines\Pipes\Validation;

use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\OrderManagement\src\Exceptions\OrderValidationException;
use App\Plugins\OrderManagement\src\Pipelines\Context\OrderValidationContext;
use App\Plugins\OrderManagement\src\Pipelines\Contracts\OrderValidationPipeInterface;
use Closure;

final readonly class ValidateBranchActivePipe implements OrderValidationPipeInterface
{
    public function handle(OrderValidationContext $context, Closure $next): mixed
    {
        $branch = Branch::query()->find($context->data->branchId);

        if (! $branch || ! $branch->is_active) {
            throw OrderValidationException::withMessage('The selected branch is inactive or unavailable.');
        }

        return $next($context);
    }
}
