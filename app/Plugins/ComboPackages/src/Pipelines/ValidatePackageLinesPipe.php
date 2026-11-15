<?php

declare(strict_types=1);

namespace App\Plugins\ComboPackages\src\Pipelines;

use App\Plugins\CartEngine\src\Models\CartItem;
use App\Plugins\ComboPackages\src\Cart\PackageCartLineHandler;
use App\Plugins\ComboPackages\src\Models\Package;
use App\Plugins\ComboPackages\src\Services\PackageAvailabilityService;
use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\OrderManagement\src\Exceptions\OrderValidationException;
use App\Plugins\OrderManagement\src\Pipelines\Context\OrderValidationContext;
use App\Plugins\OrderManagement\src\Pipelines\Contracts\OrderValidationPipeInterface;
use Closure;

final class ValidatePackageLinesPipe implements OrderValidationPipeInterface
{
    public function __construct(
        private readonly PackageCartLineHandler $handler,
        private readonly PackageAvailabilityService $availabilityService
    ) {}

    public function handle(OrderValidationContext $context, Closure $next): mixed
    {
        // Concurrency note: two simultaneous checkouts can both pass the daily check. Accept this.

        $packageQuantities = [];
        $packages = [];

        foreach ($context->lines as $line) {
            if ($line->type !== 'package') {
                continue;
            }

            $cartItem = CartItem::with(['components.product', 'components.variant'])->find($line->itemId);
            if ($cartItem === null) {
                $msg = app()->getLocale() === 'ar'
                    ? 'عنصر الباقة غير موجود في السلة.'
                    : 'The package item was not found in the cart.';
                throw OrderValidationException::withMessage($msg);
            }

            $reason = $this->handler->unavailableReason($cartItem);
            if ($reason !== null) {
                throw OrderValidationException::withMessage($reason);
            }

            $packageId = (int) $cartItem->line_reference_id;
            $qty = (int) $cartItem->quantity;

            $packageQuantities[$packageId] = ($packageQuantities[$packageId] ?? 0) + $qty;

            if (! isset($packages[$packageId])) {
                $package = Package::with(['availabilities', 'branches'])->find($packageId);
                if ($package === null) {
                    $msg = app()->getLocale() === 'ar'
                        ? 'الباقة غير متاحة حالياً.'
                        : 'The selected package is unavailable.';
                    throw OrderValidationException::withMessage($msg);
                }
                $packages[$packageId] = $package;
            }
        }

        $now = now();
        $branchId = (int) $context->data->branchId;
        $orderType = (string) $context->data->type;

        foreach ($packages as $packageId => $package) {
            $packageName = (app()->getLocale() === 'ar' && ! empty($package->name_ar))
                ? $package->name_ar
                : $package->name;

            // Check available_now
            if (! $this->availabilityService->availableNow($package, $now)) {
                $reason = $this->availabilityService->unavailableReason($package, $now);
                if ($reason === 'outside_hours') {
                    $msg = app()->getLocale() === 'ar'
                        ? "باقة {$packageName} غير متاحة في هذا الوقت."
                        : "Combo {$packageName} is not available at this time.";
                } elseif ($reason === 'sold_out_today') {
                    $msg = app()->getLocale() === 'ar'
                        ? "نفدت كمية باقة {$packageName} لليوم."
                        : "Combo {$packageName} is sold out for today.";
                } else {
                    $msg = app()->getLocale() === 'ar'
                        ? "باقة {$packageName} غير متاحة حالياً."
                        : "Combo {$packageName} is currently unavailable.";
                }
                throw OrderValidationException::withMessage($msg);
            }

            // Check branch: package_branch (empty = all)
            if ($package->branches->isNotEmpty() && ! $package->branches->pluck('id')->contains($branchId)) {
                $branch = Branch::find($branchId);
                $branchName = $branch ? (string) $branch->name : (string) $branchId;
                $msg = app()->getLocale() === 'ar'
                    ? "باقة {$packageName} غير متاحة لفرع {$branchName}."
                    : "Combo {$packageName} is not available for branch {$branchName}.";
                throw OrderValidationException::withMessage($msg);
            }

            // Check order type: allowed_order_types (null or empty = all)
            $allowedOrderTypes = $package->allowed_order_types;
            if (! empty($allowedOrderTypes) && ! in_array($orderType, $allowedOrderTypes, true)) {
                if (count($allowedOrderTypes) === 1 && in_array('web_delivery', $allowedOrderTypes, true)) {
                    $msg = app()->getLocale() === 'ar'
                        ? "باقة {$packageName} متاحة للتوصيل فقط."
                        : "Combo {$packageName} is only available for delivery.";
                } elseif (count($allowedOrderTypes) === 1 && in_array('web_takeaway', $allowedOrderTypes, true)) {
                    $msg = app()->getLocale() === 'ar'
                        ? "باقة {$packageName} متاحة للاستلام من الفرع فقط."
                        : "Combo {$packageName} is only available for takeaway.";
                } else {
                    $msg = app()->getLocale() === 'ar'
                        ? "باقة {$packageName} غير متاحة لنوع الطلب المحدد."
                        : "Combo {$packageName} is not available for the selected order type.";
                }
                throw OrderValidationException::withMessage($msg);
            }

            $totalQty = $packageQuantities[$packageId];

            // Check max_per_order
            if ($package->max_per_order !== null && $totalQty > $package->max_per_order) {
                $msg = app()->getLocale() === 'ar'
                    ? "الكمية الإجمالية لباقة {$packageName} تتجاوز الحد الأقصى المسموح به ({$package->max_per_order}) لكل طلب."
                    : "The total quantity for combo {$packageName} exceeds the maximum allowed ({$package->max_per_order}) per order.";
                throw OrderValidationException::withMessage($msg);
            }

            // Check daily_limit / remaining_today
            $remainingToday = $this->availabilityService->remainingToday($package, $now);
            if ($remainingToday !== null && $totalQty > $remainingToday) {
                $msg = app()->getLocale() === 'ar'
                    ? "الكمية المطلوبة لباقة {$packageName} تتجاوز الكمية المتبقية لليوم ({$remainingToday})."
                    : "The requested quantity for combo {$packageName} exceeds the remaining daily limit ({$remainingToday}).";
                throw OrderValidationException::withMessage($msg);
            }
        }

        return $next($context);
    }
}
