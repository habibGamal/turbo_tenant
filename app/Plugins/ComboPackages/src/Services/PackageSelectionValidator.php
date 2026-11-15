<?php

declare(strict_types=1);

namespace App\Plugins\ComboPackages\src\Services;

use App\Plugins\CartEngine\src\Services\Validators\Rules\ExtrasRule;
use App\Plugins\CatalogProducts\src\Models\ProductVariant;
use App\Plugins\ComboPackages\src\Models\Package;
use App\Plugins\ComboPackages\src\Models\PackageGroup;
use App\Plugins\ComboPackages\src\Models\PackageItem;
use Illuminate\Validation\ValidationException;

final class PackageSelectionValidator
{
    /** @var array<int, array{package_item_id: int, item: PackageItem, extras: list<array{id: int, quantity: int}>, notes: ?string}> */
    private array $lastAllGroupSelections = [];

    public function __construct(
        private readonly ExtrasRule $extrasRule = new ExtrasRule()
    ) {}

    /**
     * @return array<int, array{package_item_id: int, item: PackageItem, extras: list<array{id: int, quantity: int}>, notes: ?string}>
     */
    public function getLastAllGroupSelections(): array
    {
        return $this->lastAllGroupSelections;
    }

    /**
     * Validate client selections against a sellable package.
     *
     * @param  array<int, array<string, mixed>>  $selections
     * @param  array<int, array{package_item_id: int, item: PackageItem, extras: list<array{id: int, quantity: int}>, notes: ?string}>  $allGroupSelections
     * @return list<array{package_item_id: int, quantity: int, variant_id: ?int, item: PackageItem, variant: ?ProductVariant, extras: list<array{id: int, quantity: int}>, notes: ?string}>
     *
     * @throws ValidationException
     */
    public function validate(Package $package, array $selections, array &$allGroupSelections = []): array
    {
        $allGroupSelections = [];
        $this->lastAllGroupSelections = [];
        $errors = [];

        if (! (bool) $package->is_active || $package->trashed()) {
            $errors['package_id'] = [$this->trans('This package is currently unavailable.', 'هذه الباقة غير متاحة حالياً.')];
            throw ValidationException::withMessages($errors);
        }

        $now = now();
        if ($package->valid_from !== null && $package->valid_from->isAfter($now)) {
            $errors['package_id'] = [$this->trans('This package is not yet available.', 'هذه الباقة غير متاحة بعد.')];
            throw ValidationException::withMessages($errors);
        }

        if ($package->valid_until !== null && $package->valid_until->isBefore($now)) {
            $errors['package_id'] = [$this->trans('This package has expired.', 'انتهت صلاحية هذه الباقة.')];
            throw ValidationException::withMessages($errors);
        }

        $package->loadMissing([
            'groups.items.product.variants',
            'groups.items.product.extraOption.items',
            'groups.items.product',
            'groups.items.variant',
        ]);

        /** @var array<int, PackageGroup> $groupsById */
        $groupsById = [];
        /** @var array<int, PackageItem> $itemsById */
        $itemsById = [];
        /** @var array<int, PackageGroup> $itemGroupMap */
        $itemGroupMap = [];

        foreach ($package->groups as $group) {
            $groupsById[(int) $group->id] = $group;
            foreach ($group->items as $item) {
                $item->setRelation('group', $group);
                $itemsById[(int) $item->id] = $item;
                $itemGroupMap[(int) $item->id] = $group;
            }
        }

        /** @var array<int, list<array{index: int, item: PackageItem, quantity: int, variant_id: ?int, variant: ?ProductVariant, extras: list<array{id: int, quantity: int}>, notes: ?string}>> $picksByGroup */
        $picksByGroup = [];
        /** @var array<int, array<int, bool>> $seenItemInGroup */
        $seenItemInGroup = [];

        foreach ($selections as $index => $selection) {
            $packageItemId = isset($selection['package_item_id']) && is_numeric($selection['package_item_id'])
                ? (int) $selection['package_item_id']
                : null;

            if ($packageItemId === null || ! isset($itemsById[$packageItemId])) {
                $errors["selections.{$index}.package_item_id"] = [
                    $this->trans('The selected option is invalid or no longer available.', 'الخيار المختار غير صالح أو لم يعد متاحاً.'),
                ];

                continue;
            }

            $item = $itemsById[$packageItemId];
            $group = $itemGroupMap[$packageItemId];

            // 1. Notes validation
            $notes = null;
            if (array_key_exists('notes', $selection) && $selection['notes'] !== null) {
                if (! is_string($selection['notes'])) {
                    $errors["selections.{$index}.notes"] = [
                        $this->trans('Notes must be a string.', 'الملاحظات يجب أن تكون نصاً.'),
                    ];
                } else {
                    $trimmedNotes = mb_trim($selection['notes']);
                    if ($trimmedNotes !== '') {
                        if (mb_strlen($trimmedNotes) > 200) {
                            $errors["selections.{$index}.notes"] = [
                                $this->trans('Notes must not exceed 200 characters.', 'الملاحظات يجب ألا تتجاوز 200 حرف.'),
                            ];
                        }
                        $notes = $trimmedNotes;
                    }
                }
            }

            // 2. Extras validation
            $extrasList = [];
            $extrasMalformed = false;
            if (array_key_exists('extras', $selection) && $selection['extras'] !== null) {
                if (! is_array($selection['extras'])) {
                    $errors["selections.{$index}.extras"] = [
                        $this->trans('Extras must be an array.', 'الإضافات يجب أن تكون مصفوفة.'),
                    ];
                    $extrasMalformed = true;
                } else {
                    foreach ($selection['extras'] as $extraItem) {
                        if (! is_array($extraItem) || ! isset($extraItem['id']) || ! is_numeric($extraItem['id']) || ! isset($extraItem['quantity']) || ! is_numeric($extraItem['quantity']) || (int) $extraItem['quantity'] < 1) {
                            $errors["selections.{$index}.extras"] = [
                                $this->trans('Invalid extra option format.', 'تنسيق الإضافات غير صالح.'),
                            ];
                            $extrasMalformed = true;
                            break;
                        }
                        $extrasList[] = [
                            'id' => (int) $extraItem['id'],
                            'quantity' => (int) $extraItem['quantity'],
                        ];
                    }
                }
            }

            if (! $extrasMalformed) {
                if (! (bool) ($item->allow_extras ?? false)) {
                    if (! empty($extrasList)) {
                        $errors["selections.{$index}.extras"] = [
                            $this->trans('Extras are not allowed for this item.', 'الإضافات غير مسموح بها لهذا العنصر.'),
                        ];
                    }
                } else {
                    $product = $item->product;
                    if ($product !== null) {
                        $ruleErrors = $this->extrasRule->validate($product, $extrasList);
                        if (! empty($ruleErrors['extras'])) {
                            $errors["selections.{$index}.extras"] = $ruleErrors['extras'];
                        }
                    }
                }
            }

            // 'all' group items are added automatically by the server.
            // Client selections for 'all' group items only attach extras and notes; quantity is ignored and group counts are unchanged.
            if ($group->selection_type === 'all') {
                $allGroupSelections[$packageItemId] = [
                    'package_item_id' => $packageItemId,
                    'item' => $item,
                    'extras' => $extrasList,
                    'notes' => $notes,
                ];

                continue;
            }

            $rawQuantity = $selection['quantity'] ?? null;
            if (! is_numeric($rawQuantity) || (int) $rawQuantity < 1) {
                $errors["selections.{$index}.package_item_id"] = [
                    $this->trans('Quantity must be at least 1.', 'يجب أن تكون الكمية 1 على الأقل.'),
                ];

                continue;
            }

            $quantity = (int) $rawQuantity;
            $hasVariantId = array_key_exists('variant_id', $selection) && $selection['variant_id'] !== null;
            $variantId = $hasVariantId && is_numeric($selection['variant_id']) ? (int) $selection['variant_id'] : null;

            $chosenVariant = null;

            if ($item->variant_mode === 'customer_choice') {
                if ($variantId === null) {
                    $errors["selections.{$index}.package_item_id"] = [
                        $this->trans('A variant must be selected for this item.', 'يجب اختيار تنويع لهذا العنصر.'),
                    ];

                    continue;
                }

                $availableVariants = $item->product?->variants
                    ? $item->product->variants->where('is_available', true)->keyBy('id')
                    : collect();

                if (! $availableVariants->has($variantId)) {
                    $errors["selections.{$index}.package_item_id"] = [
                        $this->trans('The selected variant is invalid or unavailable.', 'التنويع المختار غير صالح أو غير متاح.'),
                    ];

                    continue;
                }

                $chosenVariant = $availableVariants->get($variantId);
            } else {
                // fixed item: variant_id must be omitted / null
                if ($hasVariantId) {
                    $errors["selections.{$index}.package_item_id"] = [
                        $this->trans('A variant cannot be specified for a fixed item.', 'لا يمكن اختيار تنويع لعنصر ثابت.'),
                    ];

                    continue;
                }
            }

            $groupId = (int) $group->id;

            // Check duplicate when !allow_repeats
            if (! (bool) $group->allow_repeats) {
                if (isset($seenItemInGroup[$groupId][$packageItemId]) || $quantity > 1) {
                    $errors["selections.{$index}.package_item_id"] = [
                        $this->trans('This item cannot be selected more than once.', 'لا يمكن تكرار اختيار هذا العنصر.'),
                    ];

                    continue;
                }
                $seenItemInGroup[$groupId][$packageItemId] = true;
            }

            $picksByGroup[$groupId][] = [
                'index' => $index,
                'item' => $item,
                'quantity' => $quantity,
                'variant_id' => $variantId,
                'variant' => $chosenVariant,
                'extras' => $extrasList,
                'notes' => $notes,
            ];
        }

        // Validate group counts for conditional groups
        foreach ($groupsById as $groupId => $group) {
            if ($group->selection_type === 'all') {
                continue;
            }

            $groupPicks = $picksByGroup[$groupId] ?? [];
            $totalPickedQty = 0;
            foreach ($groupPicks as $pick) {
                $totalPickedQty += $pick['quantity'];
            }

            if ($group->selection_type === 'choose_one') {
                if ($totalPickedQty !== 1 || count($groupPicks) !== 1) {
                    $errors["groups.{$groupId}"] = [
                        $this->trans('You must choose exactly one option for this group.', 'يجب اختيار عنصر واحد فقط لهذه المجموعة.'),
                    ];
                }
            } elseif ($group->selection_type === 'choose_multiple') {
                $min = $group->min_selections ?? 0;
                $max = $group->max_selections !== null ? (int) $group->max_selections : $group->items->count();

                if ($totalPickedQty < $min) {
                    $errors["groups.{$groupId}"] = [
                        $this->trans("You must choose at least {$min} option(s) for this group.", "يجب اختيار {$min} عناصر على الأقل لهذه المجموعة."),
                    ];
                } elseif ($totalPickedQty > $max) {
                    $errors["groups.{$groupId}"] = [
                        $this->trans("You cannot choose more than {$max} option(s) for this group.", "لا يمكن اختيار أكثر من {$max} عناصر لهذه المجموعة."),
                    ];
                }
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        $this->lastAllGroupSelections = $allGroupSelections;

        // Normalize and merge picks
        /** @var array<string, array{package_item_id: int, quantity: int, variant_id: ?int, item: PackageItem, variant: ?ProductVariant, extras: list<array{id: int, quantity: int}>, notes: ?string}> $normalized */
        $normalized = [];

        foreach ($picksByGroup as $groupPicks) {
            foreach ($groupPicks as $pick) {
                $item = $pick['item'];
                $variantId = $pick['variant_id'];
                $sortedExtras = $pick['extras'];
                usort($sortedExtras, fn ($a, $b) => $a['id'] <=> $b['id']);
                $extrasJson = json_encode($sortedExtras);
                $notesStr = (string) ($pick['notes'] ?? '');
                $key = "{$item->id}:".($variantId ?? 'null').":{$extrasJson}:{$notesStr}";

                if (isset($normalized[$key])) {
                    $normalized[$key]['quantity'] += $pick['quantity'];
                } else {
                    $normalized[$key] = [
                        'package_item_id' => (int) $item->id,
                        'quantity' => $pick['quantity'],
                        'variant_id' => $variantId,
                        'item' => $item,
                        'variant' => $pick['variant'],
                        'extras' => $sortedExtras,
                        'notes' => $pick['notes'],
                    ];
                }
            }
        }

        $result = array_values($normalized);
        usort($result, function (array $a, array $b): int {
            if ($a['package_item_id'] !== $b['package_item_id']) {
                return $a['package_item_id'] <=> $b['package_item_id'];
            }

            if (($a['variant_id'] ?? 0) !== ($b['variant_id'] ?? 0)) {
                return ($a['variant_id'] ?? 0) <=> ($b['variant_id'] ?? 0);
            }

            return serialize($a) <=> serialize($b);
        });

        return $result;
    }

    private function trans(string $en, string $ar): string
    {
        return app()->getLocale() === 'ar' ? $ar : $en;
    }
}
