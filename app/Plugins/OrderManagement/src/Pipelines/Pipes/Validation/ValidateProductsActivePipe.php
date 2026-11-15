<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Pipelines\Pipes\Validation;

use App\Plugins\CartEngine\src\DTOs\CartLine;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\OrderManagement\src\Exceptions\OrderValidationException;
use App\Plugins\OrderManagement\src\Pipelines\Context\OrderValidationContext;
use App\Plugins\OrderManagement\src\Pipelines\Contracts\OrderValidationPipeInterface;
use Closure;

final class ValidateProductsActivePipe implements OrderValidationPipeInterface
{
    public function handle(OrderValidationContext $context, Closure $next): mixed
    {
        $productIds = collect($context->lines)
            ->map(fn (CartLine $line): ?int => $line->productId)
            ->filter()
            ->unique()
            ->values();

        if ($productIds->isNotEmpty()) {
            $inactiveNames = Product::query()
                ->whereIn('id', $productIds)
                ->where('is_active', false)
                ->pluck('name')
                ->all();

            if (! empty($inactiveNames)) {
                throw OrderValidationException::withMessage('Some products are unavailable: '.implode(', ', $inactiveNames));
            }
        }

        return $next($context);
    }
}
