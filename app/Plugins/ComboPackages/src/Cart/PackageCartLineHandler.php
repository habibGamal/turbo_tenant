<?php

declare(strict_types=1);

namespace App\Plugins\ComboPackages\src\Cart;

use App\Plugins\CartEngine\src\Contracts\CartLineTypeHandler;
use App\Plugins\CartEngine\src\DTOs\CartLine;
use App\Plugins\CartEngine\src\DTOs\CartLineComponent;
use App\Plugins\CartEngine\src\DTOs\CartLineExtra;
use App\Plugins\CartEngine\src\DTOs\PreparedCartLine;
use App\Plugins\CartEngine\src\Models\CartItem;
use App\Plugins\CartEngine\src\Services\Contracts\CartContextResolverInterface;
use App\Plugins\CatalogProducts\src\Models\ExtraOptionItem;
use App\Plugins\ComboPackages\src\Models\Package;
use App\Plugins\ComboPackages\src\Models\PackageItem;
use App\Plugins\ComboPackages\src\Services\PackageAvailabilityService;
use App\Plugins\ComboPackages\src\Services\PackageLinePricer;
use App\Plugins\ComboPackages\src\Services\PackageSelectionValidator;
use App\Plugins\ComboPackages\src\Services\SellablePackageFilter;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class PackageCartLineHandler implements CartLineTypeHandler
{
    public function __construct(
        private readonly SellablePackageFilter $sellableFilter,
        private readonly PackageSelectionValidator $selectionValidator,
        private readonly PackageLinePricer $pricer,
        private readonly PackageAvailabilityService $availabilityService,
        private readonly CartContextResolverInterface $cartContextResolver,
    ) {}

    public function type(): string
    {
        return 'package';
    }

    public function pluginId(): string
    {
        return 'combo-packages';
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws ValidationException
     */
    public function prepare(array $payload): PreparedCartLine
    {
        $packageId = isset($payload['package_id']) && is_numeric($payload['package_id'])
            ? (int) $payload['package_id']
            : null;

        if ($packageId === null) {
            $msg = app()->getLocale() === 'ar' ? 'الباقة مطلوبة.' : 'Package ID is required.';
            throw ValidationException::withMessages(['package_id' => [$msg]]);
        }

        $package = Package::sellable()->find($packageId);
        if ($package === null) {
            $msg = app()->getLocale() === 'ar' ? 'الباقة غير متاحة حالياً.' : 'The selected package is unavailable.';
            throw ValidationException::withMessages(['package_id' => [$msg]]);
        }

        $filteredPackage = $this->sellableFilter->filterOne($package);
        if ($filteredPackage === null) {
            $msg = app()->getLocale() === 'ar' ? 'الباقة غير متاحة حالياً.' : 'The selected package is unavailable.';
            throw ValidationException::withMessages(['package_id' => [$msg]]);
        }

        if (! $this->availabilityService->availableNow($package)) {
            $reason = $this->availabilityService->unavailableReason($package);
            if ($reason === 'sold_out_today') {
                $msg = app()->getLocale() === 'ar' ? 'نفدت كمية هذه الباقة اليوم.' : 'This package is sold out for today.';
            } elseif ($reason === 'outside_hours') {
                $msg = app()->getLocale() === 'ar' ? 'الباقة غير متاحة في هذا الوقت.' : 'The package is not available at this time.';
            } else {
                $msg = app()->getLocale() === 'ar' ? 'الباقة غير متاحة حالياً.' : 'The selected package is unavailable.';
            }
            throw ValidationException::withMessages(['package_id' => [$msg]]);
        }

        $newQuantity = isset($payload['quantity']) && is_numeric($payload['quantity'])
            ? (int) $payload['quantity']
            : 1;

        if ($newQuantity < 1) {
            $newQuantity = 1;
        }

        $user = auth()->user();
        $cart = $this->cartContextResolver->resolveCart($user);
        $existingQty = 0;
        if ($cart !== null) {
            $existingQty = (int) $cart->items()
                ->where('line_type', 'package')
                ->where('line_reference_id', $package->id)
                ->sum('quantity');
        }

        $totalQty = $existingQty + $newQuantity;

        if ($package->max_per_order !== null && $totalQty > $package->max_per_order) {
            $msg = app()->getLocale() === 'ar'
                ? "الكمية المطلوبة تتجاوز الحد الأقصى المسموح به لهذه الباقة ({$package->max_per_order}) لكل طلب."
                : "The requested quantity exceeds the maximum allowed ({$package->max_per_order}) per order for this package.";
            throw ValidationException::withMessages(['quantity' => [$msg]]);
        }

        $remainingToday = $this->availabilityService->remainingToday($package);
        if ($remainingToday !== null && $totalQty > $remainingToday) {
            $msg = app()->getLocale() === 'ar'
                ? "الكمية المطلوبة تتجاوز الكمية المتبقية المتاحة اليوم ({$remainingToday})."
                : "The requested quantity exceeds the remaining daily limit ({$remainingToday}).";
            throw ValidationException::withMessages(['quantity' => [$msg]]);
        }

        $rawSelections = isset($payload['selections']) && is_array($payload['selections'])
            ? $payload['selections']
            : [];

        $allGroupSelections = [];
        $normalizedPicks = $this->selectionValidator->validate($filteredPackage, $rawSelections, $allGroupSelections);

        /** @var list<CartLineComponent> $components */
        $components = [];
        $sortOrder = 0;

        // 1. Add all items from 'all' groups automatically
        foreach ($filteredPackage->groups as $group) {
            if ($group->selection_type !== 'all') {
                continue;
            }

            foreach ($group->items as $item) {
                $product = $item->product;
                $variant = $item->variant;
                $quantity = (float) $item->quantity;
                $unitPriceAdjustment = $this->pricer->calculateComponentAdjustment($item, $variant, 1);

                $customization = $allGroupSelections[(int) $item->id] ?? null;
                $extrasPayload = $customization['extras'] ?? [];
                $notes = $customization['notes'] ?? null;

                $cartLineExtras = [];
                if (! empty($extrasPayload) && $product !== null && $product->extraOption !== null) {
                    $itemMap = $product->extraOption->items->keyBy('id');
                    foreach ($extrasPayload as $extraData) {
                        $extraOptionItem = $itemMap->get($extraData['id']);
                        if ($extraOptionItem !== null) {
                            $cartLineExtras[] = new CartLineExtra(
                                extraOptionItemId: (int) $extraOptionItem->id,
                                name: (string) $extraOptionItem->name,
                                nameAr: $extraOptionItem->name_ar !== null ? (string) $extraOptionItem->name_ar : null,
                                unitPrice: (float) $extraOptionItem->price,
                                quantity: (int) $extraData['quantity']
                            );
                        }
                    }
                }

                $components[] = new CartLineComponent(
                    sourceId: (int) $item->id,
                    groupName: (string) ($group->name ?? ''),
                    groupNameAr: $group->name_ar !== null ? (string) $group->name_ar : null,
                    productId: (int) $item->product_id,
                    variantId: $item->variant_id !== null ? (int) $item->variant_id : null,
                    productName: (string) ($product?->name ?? ''),
                    productNameAr: $product?->name_ar !== null ? (string) $product->name_ar : null,
                    variantName: $variant?->name !== null ? (string) $variant->name : null,
                    variantNameAr: $variant?->name_ar !== null ? (string) $variant->name_ar : null,
                    quantity: $quantity,
                    unitPriceAdjustment: $unitPriceAdjustment,
                    sortOrder: $sortOrder++,
                    extras: $cartLineExtras,
                    notes: $notes
                );
            }
        }

        // 2. Add selected picks
        foreach ($normalizedPicks as $pick) {
            $item = $pick['item'];
            $group = $item->group;
            $selectionQty = $pick['quantity'];
            $variant = $pick['variant'] ?? $item->variant;
            $quantity = (float) ($item->quantity * $selectionQty);
            $unitPriceAdjustment = $this->pricer->calculateComponentAdjustment($item, $variant, $selectionQty);

            $cartLineExtras = [];
            $extrasPayload = $pick['extras'] ?? [];
            if (! empty($extrasPayload) && $item->product !== null && $item->product->extraOption !== null) {
                $itemMap = $item->product->extraOption->items->keyBy('id');
                foreach ($extrasPayload as $extraData) {
                    $extraOptionItem = $itemMap->get($extraData['id']);
                    if ($extraOptionItem !== null) {
                        $cartLineExtras[] = new CartLineExtra(
                            extraOptionItemId: (int) $extraOptionItem->id,
                            name: (string) $extraOptionItem->name,
                            nameAr: $extraOptionItem->name_ar !== null ? (string) $extraOptionItem->name_ar : null,
                            unitPrice: (float) $extraOptionItem->price,
                            quantity: (int) $extraData['quantity']
                        );
                    }
                }
            }

            $components[] = new CartLineComponent(
                sourceId: (int) $item->id,
                groupName: (string) ($group?->name ?? ''),
                groupNameAr: $group?->name_ar !== null ? (string) $group->name_ar : null,
                productId: (int) $item->product_id,
                variantId: $variant !== null ? (int) $variant->id : ($item->variant_id !== null ? (int) $item->variant_id : null),
                productName: (string) ($item->product?->name ?? ''),
                productNameAr: $item->product?->name_ar !== null ? (string) $item->product->name_ar : null,
                variantName: $variant?->name !== null ? (string) $variant->name : null,
                variantNameAr: $variant?->name_ar !== null ? (string) $variant->name_ar : null,
                quantity: $quantity,
                unitPriceAdjustment: $unitPriceAdjustment,
                sortOrder: $sortOrder++,
                extras: $cartLineExtras,
                notes: $pick['notes'] ?? null
            );
        }

        // 3. Signature: sha1 of normalized, sorted picks + all group customizations
        $sigData = [];
        foreach ($normalizedPicks as $p) {
            $sortedExtras = $p['extras'] ?? [];
            usort($sortedExtras, fn ($a, $b) => $a['id'] <=> $b['id']);
            $sigData[] = [
                'package_item_id' => $p['package_item_id'],
                'quantity' => $p['quantity'],
                'variant_id' => $p['variant_id'],
                'extras' => $sortedExtras,
                'notes' => $p['notes'] ?? null,
            ];
        }

        foreach ($allGroupSelections as $itemId => $allSel) {
            if (! empty($allSel['extras']) || ! empty($allSel['notes'])) {
                $sortedExtras = $allSel['extras'] ?? [];
                usort($sortedExtras, fn ($a, $b) => $a['id'] <=> $b['id']);
                $sigData[] = [
                    'package_item_id' => (int) $itemId,
                    'quantity' => 1,
                    'variant_id' => null,
                    'extras' => $sortedExtras,
                    'notes' => $allSel['notes'] ?? null,
                ];
            }
        }

        usort($sigData, function (array $a, array $b): int {
            if ($a['package_item_id'] !== $b['package_item_id']) {
                return $a['package_item_id'] <=> $b['package_item_id'];
            }

            if (($a['variant_id'] ?? 0) !== ($b['variant_id'] ?? 0)) {
                return ($a['variant_id'] ?? 0) <=> ($b['variant_id'] ?? 0);
            }

            return serialize($a) <=> serialize($b);
        });

        $signature = sha1((string) json_encode($sigData));

        return new PreparedCartLine(
            type: 'package',
            referenceId: (int) $filteredPackage->id,
            signature: $signature,
            components: $components
        );
    }

    public function buildLine(CartItem $item): CartLine
    {
        $package = Package::withTrashed()->find($item->line_reference_id);
        if ($package === null) {
            // Never price a combo whose package row is gone; CartEngine drops the line instead.
            throw new RuntimeException("Package {$item->line_reference_id} for cart item {$item->id} no longer exists.");
        }

        $item->loadMissing([
            'components.product',
            'components.variant',
        ]);

        $sourceIds = $item->components->pluck('source_id')->filter()->unique()->all();
        $packageItems = ! empty($sourceIds)
            ? PackageItem::with(['group', 'product', 'variant'])->whereIn('id', $sourceIds)->get()->keyBy('id')
            : collect();

        $variantIds = $item->components->pluck('variant_id')->filter()->unique()->all();
        $variants = ! empty($variantIds)
            ? \App\Plugins\CatalogProducts\src\Models\ProductVariant::whereIn('id', $variantIds)->get()->keyBy('id')
            : collect();

        $productIds = $item->components->pluck('product_id')->filter()->unique()->all();
        $products = ! empty($productIds)
            ? \App\Plugins\CatalogProducts\src\Models\Product::whereIn('id', $productIds)->get()->keyBy('id')
            : collect();

        // Reprice extras from live catalog
        $extraItemIds = [];
        foreach ($item->components as $dbComp) {
            if (! empty($dbComp->extras) && is_array($dbComp->extras)) {
                foreach ($dbComp->extras as $e) {
                    if (isset($e['id'])) {
                        $extraItemIds[] = (int) $e['id'];
                    }
                }
            }
        }

        $extraOptionItems = ! empty($extraItemIds)
            ? ExtraOptionItem::whereIn('id', array_unique($extraItemIds))->get()->keyBy('id')
            : collect();

        /** @var list<CartLineComponent> $components */
        $components = [];

        foreach ($item->components as $dbComp) {
            /** @var PackageItem|null $packageItem */
            $packageItem = $dbComp->source_id !== null ? $packageItems->get($dbComp->source_id) : null;
            $product = ($dbComp->product_id ? $products->get($dbComp->product_id) : null) ?? $dbComp->product ?? $packageItem?->product;
            $variant = ($dbComp->variant_id ? $variants->get($dbComp->variant_id) : null) ?? $dbComp->variant ?? $packageItem?->variant;

            if ($packageItem !== null && $product !== null) {
                $group = $packageItem->group;
                if ($group?->selection_type === 'all') {
                    $unitPriceAdjustment = $this->pricer->calculateComponentAdjustment($packageItem, $variant, 1, $product);
                } else {
                    $baseQty = max(1, (int) $packageItem->quantity);
                    $selectionQty = max(1, (int) round((float) $dbComp->quantity / $baseQty));
                    $unitPriceAdjustment = $this->pricer->calculateComponentAdjustment($packageItem, $variant, $selectionQty, $product);
                }
            } else {
                $unitPriceAdjustment = (float) $dbComp->unit_price_adjustment;
            }

            $compExtras = [];
            if (! empty($dbComp->extras) && is_array($dbComp->extras)) {
                foreach ($dbComp->extras as $e) {
                    $extraId = (int) ($e['id'] ?? 0);
                    $extraModel = $extraOptionItems->get($extraId);
                    if ($extraModel !== null) {
                        $compExtras[] = new CartLineExtra(
                            extraOptionItemId: (int) $extraModel->id,
                            name: (string) $extraModel->name,
                            nameAr: $extraModel->name_ar !== null ? (string) $extraModel->name_ar : null,
                            unitPrice: (float) $extraModel->price,
                            quantity: (int) ($e['quantity'] ?? 1)
                        );
                    }
                }
            }

            $components[] = new CartLineComponent(
                sourceId: $dbComp->source_id !== null ? (int) $dbComp->source_id : null,
                groupName: (string) ($packageItem?->group?->name ?? ''),
                groupNameAr: $packageItem?->group?->name_ar !== null ? (string) $packageItem->group->name_ar : null,
                productId: (int) $dbComp->product_id,
                variantId: $dbComp->variant_id !== null ? (int) $dbComp->variant_id : null,
                productName: (string) ($product?->name ?? ''),
                productNameAr: $product?->name_ar !== null ? (string) $product->name_ar : null,
                variantName: $variant?->name !== null ? (string) $variant->name : null,
                variantNameAr: $variant?->name_ar !== null ? (string) $variant->name_ar : null,
                quantity: (float) $dbComp->quantity,
                unitPriceAdjustment: $unitPriceAdjustment,
                sortOrder: (int) $dbComp->sort_order,
                extras: $compExtras,
                notes: $dbComp->notes
            );
        }

        $unitPrice = $this->pricer->calculateComboUnitPrice($package, $components);
        $lineQty = (float) $item->quantity;
        $subtotal = round($unitPrice * $lineQty, 2);

        return new CartLine(
            itemId: (int) $item->id,
            productId: null,
            categoryId: null,
            variantId: null,
            weightOptionValueId: null,
            weightMultiplier: 1,
            quantity: $lineQty,
            unitPrice: $unitPrice,
            extrasUnitTotal: 0.0,
            subtotal: $subtotal,
            productName: (string) ($package?->name ?? 'Package'),
            productNameAr: $package?->name_ar !== null ? (string) $package->name_ar : null,
            variantName: null,
            variantNameAr: null,
            weightLabel: null,
            notes: $item->notes,
            extras: [],
            productImage: $package?->image !== null ? (string) $package->image : null,
            sellByWeight: false,
            weightValue: null,
            type: 'package',
            referenceId: (int) ($package?->id ?? $item->line_reference_id),
            components: $components,
            couponEligible: (bool) ($package?->allow_coupons ?? false)
        );
    }

    public function unavailableReason(CartItem $item): ?string
    {
        $package = Package::find($item->line_reference_id);
        if ($package === null || $package->trashed() || ! $this->sellableFilter->isSellable($package)) {
            return app()->getLocale() === 'ar'
                ? 'الباقة غير متاحة حالياً أو انتهت صلاحيتها.'
                : 'This package is currently unavailable or has expired.';
        }

        $filteredPackage = $this->sellableFilter->filterOne($package);
        if ($filteredPackage === null) {
            return app()->getLocale() === 'ar'
                ? 'بعض عناصر الباقة لم تعد متوفرة.'
                : 'Some items in this package are no longer available.';
        }

        $item->loadMissing(['components.product.extraOption.items']);

        $sourceIds = $item->components->pluck('source_id')->filter()->unique()->all();
        $packageItems = ! empty($sourceIds)
            ? PackageItem::with(['group', 'product.extraOption.items'])->whereIn('id', $sourceIds)->get()->keyBy('id')
            : collect();

        // 1. Check 'all' group drift
        $currentAllItems = [];
        foreach ($filteredPackage->groups as $group) {
            if ($group->selection_type !== 'all') {
                continue;
            }

            foreach ($group->items as $pkgItem) {
                $currentAllItems[(int) $pkgItem->id] = (float) $pkgItem->quantity;
            }
        }

        $storedAllItems = [];
        $reconstructedSelections = [];

        foreach ($item->components as $dbComp) {
            /** @var PackageItem|null $pkgItem */
            $pkgItem = $dbComp->source_id !== null ? $packageItems->get($dbComp->source_id) : null;
            if ($pkgItem === null) {
                return app()->getLocale() === 'ar'
                    ? 'بعض عناصر الباقة لم تعد متوفرة.'
                    : 'Some items in this package are no longer available.';
            }

            if ($pkgItem->group?->selection_type === 'all') {
                $storedAllItems[(int) $pkgItem->id] = (float) $dbComp->quantity;
            } else {
                $baseQty = max(1, (int) $pkgItem->quantity);
                $selQty = max(1, (int) round((float) $dbComp->quantity / $baseQty));

                $reconstructedSelections[] = [
                    'package_item_id' => (int) $dbComp->source_id,
                    'quantity' => $selQty,
                    'variant_id' => $dbComp->variant_id !== null ? (int) $dbComp->variant_id : null,
                    'extras' => $dbComp->extras ?? [],
                    'notes' => $dbComp->notes,
                ];
            }

            // Extras re-validation: an extra removed from product's extra option makes line unavailable
            if (! empty($dbComp->extras) && is_array($dbComp->extras)) {
                $product = $dbComp->product ?? $pkgItem->product;
                if ($product === null || $product->extraOption === null) {
                    return app()->getLocale() === 'ar'
                        ? 'بعض الإضافات المحددة لم تعد متوفرة.'
                        : 'Some selected extras are no longer available.';
                }

                $availableExtraIds = $product->extraOption->items->pluck('id')->all();
                foreach ($dbComp->extras as $e) {
                    $eId = (int) ($e['id'] ?? 0);
                    if (! in_array($eId, $availableExtraIds, true)) {
                        return app()->getLocale() === 'ar'
                            ? 'بعض الإضافات المحددة لم تعد متوفرة.'
                            : 'Some selected extras are no longer available.';
                    }
                }
            }
        }

        $drift = false;
        if (count($currentAllItems) !== count($storedAllItems)) {
            $drift = true;
        } else {
            foreach ($currentAllItems as $pId => $expectedQty) {
                if (! array_key_exists($pId, $storedAllItems) || (float) $storedAllItems[$pId] !== (float) $expectedQty) {
                    $drift = true;
                    break;
                }
            }
        }

        if ($drift) {
            return app()->getLocale() === 'ar'
                ? 'تغيّرت محتويات الباقة، يرجى إضافتها من جديد.'
                : 'Package contents have changed, please re-add it.';
        }

        try {
            $this->selectionValidator->validate($filteredPackage, $reconstructedSelections);
        } catch (ValidationException $e) {
            /** @var string|null $firstError */
            $firstError = collect($e->errors())->flatten()->first();

            return $firstError ?? (app()->getLocale() === 'ar'
                ? 'خيارات الباقة المحددة لم تعد صالحة.'
                : 'The selected options for this package are no longer valid.');
        }

        return null;
    }
}
