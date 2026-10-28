<?php

declare(strict_types=1);

namespace App\Plugins\PosIntegration\src\Services\POS;

use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\OrderManagement\src\Models\OrderItem;
use App\Plugins\PosIntegration\src\Models\ProductPosMapping;
use App\Plugins\PosIntegration\src\Services\POS\Contracts\POSProductResolverInterface;
use Illuminate\Support\Collection;

final class POSProductResolver implements POSProductResolverInterface
{
    /**
     * @var Collection<int, ProductPosMapping>|null
     */
    private ?Collection $mappings = null;

    /**
     * Set preloaded mappings collection for batch processing.
     *
     * @param  Collection<int, ProductPosMapping>|null  $mappings
     */
    public function setMappings(?Collection $mappings): self
    {
        $this->mappings = $mappings;

        return $this;
    }

    /**
     * Get current preloaded mappings.
     *
     * @return Collection<int, ProductPosMapping>|null
     */
    public function getMappings(): ?Collection
    {
        return $this->mappings;
    }

    /**
     * Load all mappings for an order in a single database query.
     *
     * @return Collection<int, ProductPosMapping>
     */
    public function loadMappingsForOrder(Order $order): Collection
    {
        $items = $order->relationLoaded('items') ? $order->items : $order->items()->with('extras')->get();

        return $this->loadMappingsForItems($items, $order->branch_id);
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
        $extraOptionItemIds = [];

        foreach ($items as $item) {
            if ($item->product_id) {
                $productIds[] = $item->product_id;
            }

            if ($item->relationLoaded('extras')) {
                foreach ($item->extras as $extra) {
                    if ($extra->extra_option_item_id) {
                        $extraOptionItemIds[] = $extra->extra_option_item_id;
                    }
                }
            } else {
                foreach ($item->extras as $extra) {
                    if ($extra->extra_option_item_id) {
                        $extraOptionItemIds[] = $extra->extra_option_item_id;
                    }
                }
            }
        }

        $productIds = array_values(array_unique(array_filter($productIds)));
        $extraOptionItemIds = array_values(array_unique(array_filter($extraOptionItemIds)));

        if (empty($productIds) && empty($extraOptionItemIds)) {
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
            ->where(function ($query) use ($productIds, $extraOptionItemIds) {
                $hasCondition = false;
                if (! empty($productIds)) {
                    $query->whereIn('product_id', $productIds);
                    $hasCondition = true;
                }
                if (! empty($extraOptionItemIds)) {
                    if ($hasCondition) {
                        $query->orWhereIn('extra_option_item_id', $extraOptionItemIds);
                    } else {
                        $query->whereIn('extra_option_item_id', $extraOptionItemIds);
                    }
                }
            })
            ->get();
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
        $notesData = [];

        if (! empty($item->notes)) {
            $notesData['ملاحظات'] = $item->notes;
        }

        $weightLabel = (! empty($item->weight_label))
            ? $item->weight_label
            : ($item->weightOptionValue?->label ?? ($item->weightOptionValue ? (string) $item->weightOptionValue->value : null));

        if (($item->weight_option_value_id || ! empty($item->weight_label)) && $item->weight_multiplier && $weightLabel) {
            $notesData['الوزن'] = '('.$item->weight_multiplier.' x) '.$weightLabel;
        }

        if ($item->extras->isNotEmpty()) {
            $extraNames = $item->extras->map(function ($extra) {
                $name = (! empty($extra->extra_name))
                    ? $extra->extra_name
                    : ($extra->extraOptionItem?->name ?? 'إضافة');

                return $name.' (x'.$extra->quantity.')';
            })->join(', ');

            $notesData['اضافات'] = $extraNames;
        }

        $mappings = $this->mappings ?? $this->loadMappingsForItems([$item], $branchId);

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
                $variantName = (! empty($item->variant_name)) ? $item->variant_name : $item->variant?->name;
                if ($variantName) {
                    $notesData['النوع'] = $variantName;
                }
            }
        } elseif (! empty($item->variant_name)) {
            $notesData['النوع'] = $item->variant_name;
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

        $posRefObj = [
            [
                'productRef' => $mainProductRef,
                'quantity' => 1,
            ],
        ];

        // Resolve extras as posRefObj components
        foreach ($item->extras as $extra) {
            if (! $extra->extra_option_item_id) {
                continue;
            }

            // Respect extra_option_items.pos_mapping_type:
            // Extras with type 'notes' must NOT be added to posRefObj (they stay in the notes);
            // type 'pos_item' behaves as today.
            if ($extra->extraOptionItem?->pos_mapping_type === 'notes') {
                continue;
            }

            $extraMapping = $mappings->first(function ($m) use ($extra, $branchId) {
                return (int) $m->extra_option_item_id === (int) $extra->extra_option_item_id
                    && $branchId !== null && (int) $m->branch_id === (int) $branchId;
            }) ?? $mappings->first(function ($m) use ($extra) {
                return (int) $m->extra_option_item_id === (int) $extra->extra_option_item_id
                    && $m->branch_id === null;
            });

            if ($extraMapping) {
                $multiplier = ($item->weight_option_value_id && $item->weight_multiplier)
                    ? (float) ($extra->quantity * $item->weight_multiplier)
                    : (float) $extra->quantity;

                $posRefObj[] = [
                    'productRef' => $extraMapping->pos_item_id,
                    'quantity' => $multiplier,
                ];
            }
        }

        return [
            'quantity' => (float) $item->quantity,
            'notes' => $this->formatNotes($notesData, $item->notes),
            'posRefObj' => $posRefObj,
        ];
    }

    /**
     * Format notes data into expected POS format.
     */
    private function formatNotes(array $notesData, ?string $originalNotes): string
    {
        if (empty($notesData)) {
            return $originalNotes ?? '';
        }

        // If only original user note exists without extras/variants/weights, keep as clean string
        if (count($notesData) === 1 && isset($notesData['ملاحظات'])) {
            return (string) $notesData['ملاحظات'];
        }

        return 'json::'.json_encode($notesData, JSON_UNESCAPED_UNICODE);
    }
}
