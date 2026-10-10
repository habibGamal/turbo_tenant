<?php

declare(strict_types=1);

namespace App\Services\POS;

use App\Models\OrderItem;
use App\Models\ProductPosMapping;
use App\Services\POS\Contracts\POSProductResolverInterface;

final class POSProductResolver implements POSProductResolverInterface
{
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

        if ($item->weight_option_value_id && $item->weight_multiplier && $item->weightOptionValue) {
            $weightLabel = $item->weightOptionValue->label ?? (string) $item->weightOptionValue->value;
            $notesData['الوزن'] = '('.$item->weight_multiplier.' x) '.$weightLabel;
        }

        if ($item->extras->isNotEmpty()) {
            $extraNames = $item->extras->map(function ($extra) {
                $name = $extra->extraOptionItem?->name ?? $extra->extra_name ?? 'إضافة';

                return $name.' (x'.$extra->quantity.')';
            })->join(', ');

            $notesData['اضافات'] = $extraNames;
        }

        $mainProductRef = null;

        // 1. If item has a variant, prioritize variant-specific mapping
        if ($item->variant_id) {
            $variantMapping = ProductPosMapping::query()
                ->where('product_id', $item->product_id)
                ->where('variant_id', $item->variant_id)
                ->where(function ($query) use ($branchId) {
                    $query->where('branch_id', $branchId)
                        ->orWhereNull('branch_id');
                })
                ->orderByRaw('CASE WHEN branch_id IS NOT NULL THEN 0 ELSE 1 END')
                ->first();

            if ($variantMapping) {
                $mainProductRef = $variantMapping->pos_item_id;
            } elseif ($item->variant) {
                $notesData['النوع'] = $item->variant->name;
            }
        }

        // 2. If no variant mapping found, look for base product mapping
        if (! $mainProductRef) {
            $mainProductMapping = ProductPosMapping::query()
                ->where('product_id', $item->product_id)
                ->whereNull('variant_id')
                ->whereNull('extra_option_item_id')
                ->where(function ($query) use ($branchId) {
                    $query->where('branch_id', $branchId)
                        ->orWhereNull('branch_id');
                })
                ->orderByRaw('CASE WHEN branch_id IS NOT NULL THEN 0 ELSE 1 END')
                ->first();

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

            $extraMapping = ProductPosMapping::query()
                ->where('extra_option_item_id', $extra->extra_option_item_id)
                ->where(function ($query) use ($branchId) {
                    $query->where('branch_id', $branchId)
                        ->orWhereNull('branch_id');
                })
                ->orderByRaw('CASE WHEN branch_id IS NOT NULL THEN 0 ELSE 1 END')
                ->first();

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
