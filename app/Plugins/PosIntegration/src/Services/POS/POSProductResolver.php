<?php

declare(strict_types=1);

namespace App\Plugins\PosIntegration\src\Services\POS;

use App\Plugins\CatalogProducts\src\Models\ExtraOptionItem;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\OrderManagement\src\Models\OrderItem;
use App\Plugins\OrderManagement\src\Models\OrderItemComponent;
use App\Plugins\PosIntegration\src\Enums\PosNoteLabel;
use App\Plugins\PosIntegration\src\Models\PosLineMapping;
use App\Plugins\PosIntegration\src\Models\ProductPosMapping;
use App\Plugins\PosIntegration\src\Services\POS\Contracts\POSProductResolverInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

final class POSProductResolver implements POSProductResolverInterface
{
    /**
     * @var Collection<int, ProductPosMapping>|null
     */
    private ?Collection $mappings = null;

    /**
     * @var Collection<int, PosLineMapping>|null
     */
    private ?Collection $lineMappings = null;

    /**
     * @var array<int, string>
     */
    private array $extraMappingTypes = [];

    /**
     * Return a new resolver instance with preloaded mappings.
     *
     * @param  Collection<int, ProductPosMapping>  $mappings
     */
    public function withMappings(Collection $mappings, ?Collection $lineMappings = null): static
    {
        $clone = clone $this;
        $clone->mappings = $mappings;
        if ($lineMappings !== null) {
            $clone->lineMappings = $lineMappings;
        }
        $clone->extraMappingTypes = $this->extraMappingTypes;

        return $clone;
    }

    /**
     * Return a new resolver instance with preloaded line mappings.
     *
     * @param  Collection<int, PosLineMapping>  $lineMappings
     */
    public function withLineMappings(Collection $lineMappings): static
    {
        $clone = clone $this;
        $clone->lineMappings = $lineMappings;
        $clone->extraMappingTypes = $this->extraMappingTypes;

        return $clone;
    }

    /**
     * Load all mappings for an order in a single database query.
     *
     * @return Collection<int, ProductPosMapping>
     */
    public function loadMappingsForOrder(Order $order): Collection
    {
        $items = $order->relationLoaded('items')
            ? $order->items
            : $order->items()->with(['extras', 'components'])->get();

        $this->lineMappings = $this->loadLineMappingsForItems($items, $order->branch_id);

        return $this->loadMappingsForItems($items, $order->branch_id);
    }

    /**
     * Load all line mappings for an order in a single database query.
     *
     * @return Collection<int, PosLineMapping>
     */
    public function loadLineMappingsForOrder(Order $order): Collection
    {
        $items = $order->relationLoaded('items')
            ? $order->items
            : $order->items()->with(['components'])->get();

        return $this->loadLineMappingsForItems($items, $order->branch_id);
    }

    /**
     * Load all line mappings for given items and branch in a single database query.
     *
     * @param  iterable<OrderItem>  $items
     * @return Collection<int, PosLineMapping>
     */
    public function loadLineMappingsForItems(iterable $items, ?int $branchId): Collection
    {
        $compositeItems = [];
        foreach ($items as $item) {
            if (! $item->isProductLine() && ! empty($item->line_reference_id)) {
                $compositeItems[] = [
                    'line_type' => $item->line_type,
                    'line_reference_id' => (int) $item->line_reference_id,
                ];
            }
        }

        if (empty($compositeItems)) {
            return collect();
        }

        $lineTypes = array_values(array_unique(array_column($compositeItems, 'line_type')));
        $lineRefIds = array_values(array_unique(array_column($compositeItems, 'line_reference_id')));

        return PosLineMapping::query()
            ->where(function ($query) use ($branchId) {
                if ($branchId !== null) {
                    $query->where('branch_id', $branchId)
                        ->orWhereNull('branch_id');
                } else {
                    $query->whereNull('branch_id');
                }
            })
            ->whereIn('line_type', $lineTypes)
            ->whereIn('line_reference_id', $lineRefIds)
            ->get();
    }

    /**
     * Load all mappings for given items and branch in a single database query.
     *
     * @param  iterable<OrderItem>  $items
     * @return Collection<int, ProductPosMapping>
     */
    public function loadMappingsForItems(iterable $items, ?int $branchId): Collection
    {
        $productIds = [];
        $variantIds = [];
        $extraOptionItemIds = [];

        foreach ($items as $item) {
            if ($item->product_id) {
                $productIds[] = $item->product_id;
            }
            if ($item->variant_id) {
                $variantIds[] = $item->variant_id;
            }

            foreach ($item->extras as $extra) {
                if ($extra->extra_option_item_id) {
                    $extraOptionItemIds[] = $extra->extra_option_item_id;
                }
            }

            $components = $item->relationLoaded('components')
                ? $item->components
                : (method_exists($item, 'components') ? $item->components()->get() : []);

            foreach ($components as $component) {
                if ($component->product_id) {
                    $productIds[] = $component->product_id;
                }
                if ($component->variant_id) {
                    $variantIds[] = $component->variant_id;
                }
                if (! empty($component->extras) && is_array($component->extras)) {
                    foreach ($component->extras as $extra) {
                        $extraId = $extra['extra_option_item_id'] ?? $extra['id'] ?? null;
                        if ($extraId) {
                            $extraOptionItemIds[] = (int) $extraId;
                        }
                    }
                }
            }
        }

        $productIds = array_values(array_unique(array_filter($productIds)));
        $variantIds = array_values(array_unique(array_filter($variantIds)));
        $extraOptionItemIds = array_values(array_unique(array_filter($extraOptionItemIds)));

        if (! empty($extraOptionItemIds)) {
            $types = ExtraOptionItem::query()
                ->whereIn('id', $extraOptionItemIds)
                ->pluck('pos_mapping_type', 'id')
                ->toArray();

            foreach ($types as $id => $type) {
                $this->extraMappingTypes[(int) $id] = $type ?: 'pos_item';
            }
        }

        if (empty($productIds) && empty($variantIds) && empty($extraOptionItemIds)) {
            return collect();
        }

        return ProductPosMapping::query()
            ->where(function ($query) use ($branchId) {
                if ($branchId !== null) {
                    $query->where('branch_id', $branchId)
                        ->orWhereNull('branch_id');
                } else {
                    $query->whereNull('branch_id');
                }
            })
            ->where(function ($query) use ($productIds, $variantIds, $extraOptionItemIds) {
                // Grouped, so the OR never escapes the branch condition above.
                $hasCondition = false;
                if ($productIds !== []) {
                    $query->whereIn('product_id', $productIds);
                    $hasCondition = true;
                }
                if ($variantIds !== []) {
                    if ($hasCondition) {
                        $query->orWhereIn('variant_id', $variantIds);
                    } else {
                        $query->whereIn('variant_id', $variantIds);
                        $hasCondition = true;
                    }
                }
                if ($extraOptionItemIds !== []) {
                    if ($hasCondition) {
                        $query->orWhereIn('extra_option_item_id', $extraOptionItemIds);
                    } else {
                        $query->whereIn('extra_option_item_id', $extraOptionItemIds);
                        $hasCondition = true;
                    }
                }
            })
            ->get();
    }

    /**
     * Check if an order item is fully mapped to POS.
     */
    public function isItemMapped(OrderItem $item, ?int $branchId = null): bool
    {
        return $this->resolveOrderItem($item, $branchId) !== null;
    }

    /**
     * Resolve POS item structure for an order item according to posRefObj specifications.
     *
     * @return array{
     *     quantity: float|int,
     *     notes: string,
     *     posRefObj: array<int, array{productRef: string, quantity: float|int}>
     * }|null
     */
    public function resolveOrderItem(OrderItem $item, ?int $branchId): ?array
    {
        if (! $item->isProductLine()) {
            return $this->resolveCompositeOrderItem($item, $branchId);
        }

        $notesData = [];

        if (! empty($item->notes)) {
            $notesData[PosNoteLabel::NOTES->value] = $item->notes;
        }

        $weightLabel = (! empty($item->weight_label))
            ? $item->weight_label
            : ($item->weightOptionValue?->label ?? ($item->weightOptionValue ? (string) $item->weightOptionValue->value : null));

        if (($item->weight_option_value_id || ! empty($item->weight_label)) && $item->weight_multiplier && $weightLabel) {
            $notesData[PosNoteLabel::WEIGHT->value] = '('.$item->weight_multiplier.' x) '.$weightLabel;
        }

        $mappings = $this->mappings ?? $this->loadMappingsForItems([$item], $branchId);

        // Mapped add-ons go to the POS as their own lines (see resolveExtraLines);
        // only the rest are described in the item notes.
        $noteExtras = $item->extras->reject(fn ($extra) => $this->mappedExtraRef($extra, $mappings, $branchId) !== null);
        if ($noteExtras->isNotEmpty()) {
            $notesData[PosNoteLabel::EXTRAS->value] = $noteExtras
                ->map(fn ($extra) => $this->extraName($extra).' (x'.$this->formatQuantity((float) $extra->quantity).')')
                ->join(', ');
        }

        $mainProductRef = null;

        // 1. If item has a variant, prioritize variant-specific mapping
        if ($item->variant_id) {
            $variantMapping = $mappings->first(function ($m) use ($item, $branchId) {
                return (int) $m->product_id === (int) $item->product_id
                    && (int) $m->variant_id === (int) $item->variant_id
                    && $branchId !== null && (int) $m->branch_id === (int) $branchId;
            }) ?? $mappings->first(function ($m) use ($item) {
                return (int) $m->product_id === (int) $item->product_id
                    && (int) $m->variant_id === (int) $item->variant_id
                    && $m->branch_id === null;
            });

            if ($variantMapping) {
                $mainProductRef = $variantMapping->pos_item_id;
            } else {
                $variantName = $this->variantName($item);
                if ($variantName) {
                    $notesData[PosNoteLabel::TYPE->value] = $variantName;
                }
            }
        } elseif (($variantName = $this->variantName($item)) !== null) {
            $notesData[PosNoteLabel::TYPE->value] = $variantName;
        }

        // 2. If no variant mapping found, look for base product mapping
        if (! $mainProductRef) {
            $mainProductMapping = $mappings->first(function ($m) use ($item, $branchId) {
                return (int) $m->product_id === (int) $item->product_id
                    && $m->variant_id === null
                    && $m->extra_option_item_id === null
                    && $branchId !== null && (int) $m->branch_id === (int) $branchId;
            }) ?? $mappings->first(function ($m) use ($item) {
                return (int) $m->product_id === (int) $item->product_id
                    && $m->variant_id === null
                    && $m->extra_option_item_id === null
                    && $m->branch_id === null;
            });

            if ($mainProductMapping) {
                $mainProductRef = $mainProductMapping->pos_item_id;
            }
        }

        // If neither variant nor base product mapping exists, item cannot be mapped
        if (! $mainProductRef) {
            return null;
        }

        return [
            'quantity' => (float) $item->quantity,
            'notes' => $this->formatNotes($notesData, $item->notes),
            'posRefObj' => [
                [
                    'productRef' => $mainProductRef,
                    'quantity' => 1,
                ],
            ],
        ];
    }

    /**
     * POS lines for the mapped add-ons of a product line, one per add-on, so
     * the POS shows each add-on like the website does: its own line, its own
     * count (add-on quantity x item count, or x weight multiplier for items
     * sold by weight, matching CartPriceCalculator) and no copied notes.
     *
     * @return list<array{quantity: float|int, notes: string, posRefObj: array<int, array{productRef: string, quantity: int}>}>
     */
    public function resolveExtraLines(OrderItem $item, ?int $branchId): array
    {
        if (! $item->isProductLine() || $item->extras->isEmpty()) {
            return [];
        }

        $mappings = $this->mappings ?? $this->loadMappingsForItems([$item], $branchId);
        $soldByWeight = $item->weight_option_value_id && $item->weight_multiplier;
        $itemCount = $soldByWeight ? (float) $item->weight_multiplier : (float) $item->quantity;

        $lines = [];
        foreach ($item->extras as $extra) {
            $ref = $this->mappedExtraRef($extra, $mappings, $branchId);
            if ($ref === null) {
                continue;
            }

            $quantity = (float) $extra->quantity * $itemCount;
            $lines[] = [
                'quantity' => fmod($quantity, 1.0) === 0.0 ? (int) $quantity : $quantity,
                'notes' => '',
                'posRefObj' => [
                    [
                        'productRef' => $ref,
                        'quantity' => 1,
                    ],
                ],
            ];
        }

        return $lines;
    }

    /**
     * The POS ref an add-on is sent as, or null when it stays in the notes
     * (pos_mapping_type 'notes', no extra option item, or no mapping).
     */
    private function mappedExtraRef(mixed $extra, Collection $mappings, ?int $branchId): ?string
    {
        if (! $extra->extra_option_item_id) {
            return null;
        }

        $mappingType = $extra->relationLoaded('extraOptionItem') && $extra->extraOptionItem !== null
            ? $extra->extraOptionItem->pos_mapping_type
            : $this->getExtraMappingType((int) $extra->extra_option_item_id);

        if ($mappingType === 'notes') {
            return null;
        }

        return $this->resolveExtraMapping((int) $extra->extra_option_item_id, $mappings, $branchId)?->pos_item_id;
    }

    /**
     * Arabic name first (the POS UI is Arabic), then the snapshot name, then the catalog.
     */
    private function extraName(mixed $extra): string
    {
        foreach ([$extra->extra_name_ar ?? null, $extra->extraOptionItem?->name_ar, $extra->extra_name ?? null, $extra->extraOptionItem?->name] as $name) {
            if (filled($name)) {
                return (string) $name;
            }
        }

        return PosNoteLabel::EXTRA_DEFAULT->value;
    }

    private function variantName(OrderItem $item): ?string
    {
        foreach ([$item->variant_name_ar, $item->variant?->name_ar, $item->variant_name, $item->variant?->name] as $name) {
            if (filled($name)) {
                return (string) $name;
            }
        }

        return null;
    }

    private function formatQuantity(float $quantity): string
    {
        return fmod($quantity, 1.0) === 0.0 ? (string) (int) $quantity : (string) $quantity;
    }

    /**
     * Resolve POS item structure for a composite order item (package/combo).
     *
     * @return array{
     *     quantity: float|int,
     *     notes: string,
     *     posRefObj: array<int, array{productRef: string, quantity: float|int}>
     * }|null
     */
    private function resolveCompositeOrderItem(OrderItem $item, ?int $branchId): ?array
    {
        $lineMappings = $this->lineMappings ?? $this->loadLineMappingsForItems([$item], $branchId);

        $packageMapping = $lineMappings->first(function ($m) use ($item, $branchId) {
            return $m->line_type === $item->line_type
                && (int) $m->line_reference_id === (int) $item->line_reference_id
                && $branchId !== null && (int) $m->branch_id === (int) $branchId;
        }) ?? $lineMappings->first(function ($m) use ($item) {
            return $m->line_type === $item->line_type
                && (int) $m->line_reference_id === (int) $item->line_reference_id
                && $m->branch_id === null;
        });

        $components = $item->relationLoaded('components')
            ? $item->components
            : $item->components()->get();

        // 1. Package-level mapping: send ONE posRefObj for the combo, components with extras and notes in notes
        if ($packageMapping !== null) {
            $posRefObj = [
                [
                    'productRef' => $packageMapping->pos_item_id,
                    'quantity' => 1,
                ],
            ];

            $notesData = [];

            if (! empty($item->notes)) {
                $notesData[PosNoteLabel::NOTES->value] = $item->notes;
            }

            $componentNotesList = $components->map(function ($c) {
                $group = ! empty($c->group_name) ? $c->group_name : $c->group_name_ar;
                $product = ! empty($c->product_name) ? $c->product_name : $c->product_name_ar;
                $variant = ! empty($c->variant_name) ? $c->variant_name : $c->variant_name_ar;
                $variantStr = filled($variant) ? " ({$variant})" : '';
                $qty = (float) $c->quantity;
                $qtyStr = fmod($qty, 1.0) === 0.0 ? (string) (int) $qty : (string) $qty;
                $groupStr = filled($group) ? "{$group}: " : '';

                $cStr = "{$groupStr}{$product}{$variantStr} x{$qtyStr}";

                if (! empty($c->extras) && is_array($c->extras)) {
                    $extraParts = [];
                    foreach ($c->extras as $extra) {
                        $extraName = ! empty($extra['name']) ? $extra['name'] : ($extra['name_ar'] ?? PosNoteLabel::EXTRA_DEFAULT->value);
                        $extraQty = $extra['quantity'] ?? 1;
                        $extraParts[] = "{$extraName} (x{$extraQty})";
                    }
                    if (! empty($extraParts)) {
                        $cStr .= ' + '.implode(', ', $extraParts);
                    }
                }

                if (filled($c->notes)) {
                    $cStr .= " [{$c->notes}]";
                }

                return $cStr;
            })->join(', ');

            if (! empty($componentNotesList)) {
                $notesData[PosNoteLabel::COMPONENTS->value] = $componentNotesList;
            }

            return [
                'quantity' => (float) $item->quantity,
                'notes' => $this->formatNotes($notesData, $item->notes),
                'posRefObj' => $posRefObj,
            ];
        }

        // 2. Otherwise explode: each component -> posRefObj, component extras -> posRefObj or notes
        if ($components->isEmpty()) {
            return null;
        }

        $mappings = $this->mappings ?? $this->loadMappingsForItems([$item], $branchId);
        $posRefObj = [];
        $extraNotesByComponent = [];

        foreach ($components as $component) {
            $productRef = $this->resolveComponentRef($component, $mappings, $branchId);

            if (! $productRef) {
                Log::warning("POS mapping missing for composite line component: {$component->product_name} (product ID {$component->product_id})");

                return null;
            }

            $qty = (float) $component->quantity;
            $posRefObj[] = [
                'productRef' => $productRef,
                'quantity' => fmod($qty, 1.0) === 0.0 ? (int) $qty : $qty,
            ];

            $unmappedOrNotes = [];
            if (! empty($component->extras) && is_array($component->extras)) {
                foreach ($component->extras as $extra) {
                    $extraOptionItemId = (int) ($extra['extra_option_item_id'] ?? $extra['id'] ?? 0);
                    if (! $extraOptionItemId) {
                        continue;
                    }

                    $mappingType = $this->getExtraMappingType($extraOptionItemId);
                    $extraMapping = $mappingType === 'pos_item'
                        ? $this->resolveExtraMapping($extraOptionItemId, $mappings, $branchId)
                        : null;

                    if ($extraMapping !== null) {
                        $extraQty = (float) ($extra['quantity'] ?? 1);
                        $posRefObj[] = [
                            'productRef' => $extraMapping->pos_item_id,
                            'quantity' => fmod($extraQty, 1.0) === 0.0 ? (int) $extraQty : $extraQty,
                        ];
                    } else {
                        $unmappedOrNotes[] = $extra;
                    }
                }
            }
            $extraNotesByComponent[$component->id ?? spl_object_id($component)] = $unmappedOrNotes;
        }

        $notesData = [];

        if (! empty($item->notes)) {
            $notesData[PosNoteLabel::NOTES->value] = $item->notes;
        }

        $componentNotesList = $components->map(function ($c) use ($extraNotesByComponent) {
            $group = ! empty($c->group_name) ? $c->group_name : $c->group_name_ar;
            $product = ! empty($c->product_name) ? $c->product_name : $c->product_name_ar;
            $variant = ! empty($c->variant_name) ? $c->variant_name : $c->variant_name_ar;
            $variantStr = filled($variant) ? " ({$variant})" : '';
            $qty = (float) $c->quantity;
            $qtyStr = fmod($qty, 1.0) === 0.0 ? (string) (int) $qty : (string) $qty;
            $groupStr = filled($group) ? "{$group}: " : '';

            $cStr = "{$groupStr}{$product}{$variantStr} x{$qtyStr}";

            $unmappedOrNotes = $extraNotesByComponent[$c->id ?? spl_object_id($c)] ?? [];
            if (! empty($unmappedOrNotes)) {
                $extraParts = [];
                foreach ($unmappedOrNotes as $extra) {
                    $extraName = ! empty($extra['name']) ? $extra['name'] : ($extra['name_ar'] ?? PosNoteLabel::EXTRA_DEFAULT->value);
                    $extraQty = $extra['quantity'] ?? 1;
                    $extraParts[] = "{$extraName} (x{$extraQty})";
                }
                $cStr .= ' + '.implode(', ', $extraParts);
            }

            if (filled($c->notes)) {
                $cStr .= " [{$c->notes}]";
            }

            return $cStr;
        })->join(', ');

        if (! empty($componentNotesList)) {
            $notesData[PosNoteLabel::COMPONENTS->value] = $componentNotesList;
        }

        return [
            'quantity' => (float) $item->quantity,
            'notes' => $this->formatNotes($notesData, $item->notes),
            'posRefObj' => $posRefObj,
        ];
    }

    private function resolveExtraMapping(int $extraOptionItemId, Collection $mappings, ?int $branchId): ?ProductPosMapping
    {
        return $mappings->first(function ($m) use ($extraOptionItemId, $branchId) {
            return (int) $m->extra_option_item_id === $extraOptionItemId
                && $branchId !== null && (int) $m->branch_id === (int) $branchId;
        }) ?? $mappings->first(function ($m) use ($extraOptionItemId) {
            return (int) $m->extra_option_item_id === $extraOptionItemId
                && $m->branch_id === null;
        });
    }

    private function getExtraMappingType(int $extraOptionItemId): string
    {
        if (isset($this->extraMappingTypes[$extraOptionItemId])) {
            return $this->extraMappingTypes[$extraOptionItemId];
        }

        $type = ExtraOptionItem::query()
            ->where('id', $extraOptionItemId)
            ->value('pos_mapping_type');

        return $this->extraMappingTypes[$extraOptionItemId] = ($type ?: 'pos_item');
    }

    private function resolveComponentRef(OrderItemComponent $component, Collection $mappings, ?int $branchId): ?string
    {
        $productId = (int) $component->product_id;
        $variantId = $component->variant_id ? (int) $component->variant_id : null;

        if ($variantId !== null) {
            $variantMapping = $mappings->first(function ($m) use ($productId, $variantId, $branchId) {
                return (int) $m->product_id === $productId
                    && (int) $m->variant_id === $variantId
                    && $branchId !== null && (int) $m->branch_id === (int) $branchId;
            }) ?? $mappings->first(function ($m) use ($productId, $variantId) {
                return (int) $m->product_id === $productId
                    && (int) $m->variant_id === $variantId
                    && $m->branch_id === null;
            });

            if ($variantMapping) {
                return $variantMapping->pos_item_id;
            }
        }

        $baseMapping = $mappings->first(function ($m) use ($productId, $branchId) {
            return (int) $m->product_id === $productId
                && $m->variant_id === null
                && $m->extra_option_item_id === null
                && $branchId !== null && (int) $m->branch_id === (int) $branchId;
        }) ?? $mappings->first(function ($m) use ($productId) {
            return (int) $m->product_id === $productId
                && $m->variant_id === null
                && $m->extra_option_item_id === null
                && $m->branch_id === null;
        });

        return $baseMapping?->pos_item_id;
    }

    /**
     * Format notes data into expected POS format.
     *
     * @param  array<string, mixed>  $notesData
     */
    private function formatNotes(array $notesData, ?string $originalNotes): string
    {
        if (empty($notesData)) {
            return $originalNotes ?? '';
        }

        // If only original user note exists without extras/variants/weights, keep as clean string
        if (count($notesData) === 1 && isset($notesData[PosNoteLabel::NOTES->value])) {
            return (string) $notesData[PosNoteLabel::NOTES->value];
        }

        return 'json::'.json_encode($notesData, JSON_UNESCAPED_UNICODE);
    }
}
