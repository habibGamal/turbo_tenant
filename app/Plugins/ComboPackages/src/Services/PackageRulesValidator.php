<?php

declare(strict_types=1);

namespace App\Plugins\ComboPackages\src\Services;

use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Models\ProductVariant;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Throwable;

final class PackageRulesValidator
{
    /**
     * Validate raw form state (package + groups + items) and return Arabic errors keyed by state path.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, string>
     */
    public function validate(array $state): array
    {
        $errors = [];

        // Rule: At least one of name or name_ar
        $name = mb_trim((string) ($state['name'] ?? ''));
        $nameAr = mb_trim((string) ($state['name_ar'] ?? ''));
        if ($name === '' && $nameAr === '') {
            $errors['name'] = 'يجب إدخال اسم الباقة بالإنجليزية أو العربية على الأقل';
        }

        // Rule: Price > 0
        $price = $state['price'] ?? null;
        if ($price === null || $price === '' || ! is_numeric($price) || (float) $price <= 0) {
            $errors['price'] = 'يجب أن يكون سعر الباقة أكبر من صفر';
        }

        // Rule: valid_until > valid_from when both are set
        $validFrom = $state['valid_from'] ?? null;
        $validUntil = $state['valid_until'] ?? null;
        if (! empty($validFrom) && ! empty($validUntil)) {
            try {
                $from = Carbon::parse($validFrom);
                $until = Carbon::parse($validUntil);
                if ($until->lte($from)) {
                    $errors['valid_until'] = 'تاريخ نهاية الصلاحية يجب أن يكون بعد تاريخ البداية';
                }
            } catch (Throwable) {
                // Ignore parse failures; let standard framework date validators handle malformed strings
            }
        }

        // Rule: At least one group
        $groups = $state['groups'] ?? [];
        if (! is_array($groups) || count($groups) === 0) {
            $errors['groups'] = 'يجب إضافة مجموعة واحدة على الأقل للباقة';

            return $errors;
        }

        // Collect all product IDs and variant IDs across all items for batch querying
        $productIds = [];
        $variantIds = [];
        foreach ($groups as $group) {
            $items = $group['items'] ?? [];
            if (is_array($items)) {
                foreach ($items as $item) {
                    if (! empty($item['product_id'])) {
                        $productIds[] = (int) $item['product_id'];
                    }
                    if (! empty($item['variant_id'])) {
                        $variantIds[] = (int) $item['variant_id'];
                    }
                }
            }
        }

        $products = ! empty($productIds)
            ? Product::whereIn('id', array_unique($productIds))->with(['variants', 'extraOption'])->get()->keyBy('id')
            : collect();

        $variants = ! empty($variantIds)
            ? ProductVariant::whereIn('id', array_unique($variantIds))->get()->keyBy('id')
            : collect();

        foreach ($groups as $gKey => $group) {
            $items = $group['items'] ?? [];
            if (! is_array($items) || count($items) === 0) {
                $errors["groups.{$gKey}.items"] = 'يجب إضافة عنصر واحد على الأقل في المجموعة';

                continue;
            }

            $selectionType = $group['selection_type'] ?? 'all';
            $itemCount = count($items);
            $min = isset($group['min_selections']) && $group['min_selections'] !== '' ? (int) $group['min_selections'] : null;
            $max = isset($group['max_selections']) && $group['max_selections'] !== '' ? (int) $group['max_selections'] : null;

            // Rule: choose_one: min = max = 1
            if ($selectionType === 'choose_one') {
                if ($min !== 1) {
                    $errors["groups.{$gKey}.min_selections"] = 'الحد الأدنى لمجموعة "اختر واحد" يجب أن يكون 1';
                }
                if ($max !== 1) {
                    $errors["groups.{$gKey}.max_selections"] = 'الحد الأقصى لمجموعة "اختر واحد" يجب أن يكون 1';
                }
            }

            // Rule: choose_multiple: 0 <= min <= max, and max <= number of items in the group
            if ($selectionType === 'choose_multiple') {
                $effectiveMin = $min ?? 0;
                if ($effectiveMin < 0) {
                    $errors["groups.{$gKey}.min_selections"] = 'الحد الأدنى للاختيارات يجب أن يكون 0 أو أكثر';
                }

                if ($max === null) {
                    $errors["groups.{$gKey}.max_selections"] = 'يجب تحديد الحد الأقصى للاختيارات';
                } elseif ($max < 0) {
                    $errors["groups.{$gKey}.max_selections"] = 'الحد الأقصى للاختيارات يجب أن يكون 0 أو أكثر';
                } else {
                    if ($effectiveMin > $max) {
                        $errors["groups.{$gKey}.min_selections"] = 'الحد الأدنى للاختيارات لا يمكن أن يتجاوز الحد الأقصى';
                    }
                    $allowRepeats = ! empty($group['allow_repeats']);
                    if (! $allowRepeats && $max > $itemCount) {
                        $errors["groups.{$gKey}.max_selections"] = 'الحد الأقصى للاختيارات لا يمكن أن يتجاوز عدد عناصر المجموعة';
                    }
                }
            }

            // Rule: Default items in conditional groups (0 or between min and max; ignored for 'all')
            if (in_array($selectionType, ['choose_one', 'choose_multiple'], true)) {
                $defaultCount = 0;
                foreach ($items as $item) {
                    if (! empty($item['is_default'])) {
                        $defaultCount++;
                    }
                }

                if ($defaultCount > 0) {
                    if ($selectionType === 'choose_one') {
                        if ($defaultCount > 1) {
                            $errors["groups.{$gKey}.items"] = 'لا يمكن تحديد أكثر من عنصر افتراضي واحد في مجموعة "اختر واحد"';
                        }
                    } elseif ($selectionType === 'choose_multiple') {
                        $effectiveMin = $min ?? 0;
                        $effectiveMax = $max ?? $itemCount;
                        if ($defaultCount < $effectiveMin || $defaultCount > $effectiveMax) {
                            $errors["groups.{$gKey}.items"] = 'عدد العناصر الافتراضية يجب أن يكون 0 أو بين الحد الأدنى والحد الأقصى للاختيارات';
                        }
                    }
                }
            }

            // Item-level validations
            $seenPairs = [];
            foreach ($items as $iKey => $item) {
                $productId = isset($item['product_id']) && $item['product_id'] !== '' ? (int) $item['product_id'] : null;
                $variantId = isset($item['variant_id']) && $item['variant_id'] !== '' ? (int) $item['variant_id'] : null;
                $variantMode = $item['variant_mode'] ?? 'fixed';

                if (! $productId) {
                    $errors["groups.{$gKey}.items.{$iKey}.product_id"] = 'يجب اختيار المنتج';

                    continue;
                }

                // Check sell_by_weight (B14)
                $product = $products->get($productId);
                if ($product && (bool) $product->sell_by_weight) {
                    $errors["groups.{$gKey}.items.{$iKey}.product_id"] = 'لا يمكن استخدام منتجات تباع بالوزن في باقات التوفير';
                }

                // Rule: allow_extras requires that the product has an extra option
                if (! empty($item['allow_extras'])) {
                    if (! $product || $product->extra_option_id === null || $product->extraOption === null) {
                        $errors["groups.{$gKey}.items.{$iKey}.allow_extras"] = 'المنتج المختار لا يحتوي على خيارات إضافية';
                    }
                }

                // Rule: customer_choice items must have no variant_id and their product must have at least one variant
                if ($variantMode === 'customer_choice') {
                    if ($variantId !== null) {
                        $errors["groups.{$gKey}.items.{$iKey}.variant_id"] = 'لا يمكن تحديد تنويع مسبق عندما يكون نوع التنويع يختار العميل';
                    }
                    if ($product && $product->variants->isEmpty()) {
                        $errors["groups.{$gKey}.items.{$iKey}.variant_mode"] = 'المنتج المختار يجب أن يحتوي على تنويع واحد على الأقل ليختار العميل منه';
                    }
                }

                // Check duplicate (product_id, variant_id) pair within group
                $pairKey = $variantMode === 'customer_choice'
                    ? "{$productId}:choice"
                    : "{$productId}:".($variantId ?? 'null');

                if (isset($seenPairs[$pairKey])) {
                    $errors["groups.{$gKey}.items.{$iKey}.product_id"] = 'لا يمكن تكرار نفس المنتج والتنويع داخل نفس المجموعة';
                } else {
                    $seenPairs[$pairKey] = true;
                }

                // Check variant belongs to product
                if ($variantId !== null && $variantMode !== 'customer_choice') {
                    $variant = $variants->get($variantId);
                    if (! $variant || (int) $variant->product_id !== $productId) {
                        $errors["groups.{$gKey}.items.{$iKey}.variant_id"] = 'التنويع المحدد لا ينتمي للمنتج المختار';
                    }
                }
            }
        }

        // Rule: max_per_order >= 1 when set
        $maxPerOrder = $state['max_per_order'] ?? null;
        if ($maxPerOrder !== null && $maxPerOrder !== '') {
            if (! is_numeric($maxPerOrder) || (int) $maxPerOrder < 1) {
                $errors['max_per_order'] = 'الحد الأقصى لكل طلب يجب أن يكون 1 على الأقل';
            }
        }

        // Rule: daily_limit >= 1 when set
        $dailyLimit = $state['daily_limit'] ?? null;
        if ($dailyLimit !== null && $dailyLimit !== '') {
            if (! is_numeric($dailyLimit) || (int) $dailyLimit < 1) {
                $errors['daily_limit'] = 'الحد اليومي يجب أن يكون 1 على الأقل';
            }
        }

        // Rule: Availability windows: starts_at != ends_at, and no overlapping windows on the same day
        $availabilities = $state['availabilities'] ?? [];
        if (is_array($availabilities) && ! empty($availabilities)) {
            $windowsByDay = [];

            foreach ($availabilities as $aKey => $avail) {
                if (! is_array($avail)) {
                    continue;
                }

                $start = isset($avail['starts_at']) ? mb_substr(mb_trim((string) $avail['starts_at']), 0, 5) : '';
                $end = isset($avail['ends_at']) ? mb_substr(mb_trim((string) $avail['ends_at']), 0, 5) : '';

                if ($start !== '' && $end !== '' && $start === $end) {
                    $errors["availabilities.{$aKey}.ends_at"] = 'وقت البداية يجب ألا يتطابق مع وقت النهاية';
                    $errors["availabilities.{$aKey}"] = 'وقت البداية يجب ألا يتطابق مع وقت النهاية';
                }

                if (isset($avail['day_of_week']) && $start !== '' && $end !== '' && $start !== $end) {
                    $day = (int) $avail['day_of_week'];
                    $startMin = self::parseToMinutes($start);
                    $endMin = self::parseToMinutes($end);

                    $interval = $startMin < $endMin
                        ? [$startMin, $endMin]
                        : [$startMin, 1440];

                    $windowsByDay[$day][] = [
                        'key' => $aKey,
                        'interval' => $interval,
                    ];
                }
            }

            foreach ($windowsByDay as $day => $dayWindows) {
                $count = count($dayWindows);
                for ($i = 0; $i < $count; $i++) {
                    for ($j = $i + 1; $j < $count; $j++) {
                        $w1 = $dayWindows[$i]['interval'];
                        $w2 = $dayWindows[$j]['interval'];

                        if (max($w1[0], $w2[0]) < min($w1[1], $w2[1])) {
                            $k2 = $dayWindows[$j]['key'];
                            $errors["availabilities.{$k2}"] = 'توجد فترات توفر متداخلة في نفس اليوم';
                            $errors['availabilities'] = 'توجد فترات توفر متداخلة في نفس اليوم';
                        }
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Validate raw form state and throw ValidationException on error.
     *
     * @param  array<string, mixed>  $state
     *
     * @throws ValidationException
     */
    public function validateOrFail(array $state): void
    {
        $errors = $this->validate($state);

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    private static function parseToMinutes(string $time): int
    {
        $parts = array_map('intval', explode(':', mb_trim($time)));
        $hours = $parts[0] ?? 0;
        $minutes = $parts[1] ?? 0;

        return ($hours * 60) + $minutes;
    }
}
