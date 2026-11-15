<?php

declare(strict_types=1);

namespace App\Plugins\PosIntegration\src\Filament\Contributors;

use App\Core\Plugins\Contracts\TableColumnContributor;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * POS health column contributor for the combo packages admin table.
 *
 * NOTE on boundaries:
 * Reads package_groups and package_items tables read-only from the combo-packages
 * plugin via query builder (DB::table) without referencing ComboPackages classes,
 * adhering to strict plugin boundary rules.
 *
 * Performance & Memoization:
 * The table displays many rows per page. To prevent N+1 queries during table rendering,
 * on the first state evaluation candidate package IDs are gathered (from the active
 * Filament table records or, in headless tests, from the packages table) and all POS
 * health statuses are computed in a fixed number of queries (O(1)):
 * 1) Query pos_line_mappings for package-level mappings.
 * 2) Query package_groups, package_items, and products for packages requiring component checks.
 * 3) Query product_pos_mappings for distinct component product IDs.
 * Results are stored in a static per-request array keyed by package ID.
 */
final class PosPackageHealthColumnContributor implements TableColumnContributor
{
    /**
     * Per-request memoized cache mapping package ID to POS health data.
     *
     * @var array<int, array{status: string, tooltip: ?string}>
     */
    private static array $memoizedHealth = [];

    public static function resetCache(): void
    {
        self::$memoizedHealth = [];
    }

    /**
     * Preload POS health data for specific package IDs in a single batch.
     *
     * @param  array<int>  $packageIds
     */
    public static function preload(array $packageIds): void
    {
        self::loadHealthData($packageIds);
    }

    /**
     * Resolve POS health data for a single package ID using the memoized loader.
     *
     * @return array{status: string, tooltip: ?string}
     */
    public static function getHealthData(int $packageId, ?Column $column = null): array
    {
        if (isset(self::$memoizedHealth[self::tenantKey()][$packageId])) {
            return self::$memoizedHealth[self::tenantKey()][$packageId];
        }

        $candidateIds = [];

        if ($column !== null) {
            try {
                $records = $column->getTable()->getRecords();
                foreach ($records as $r) {
                    if ($r instanceof Model) {
                        $candidateIds[] = (int) $r->getKey();
                    } elseif (is_object($r) && isset($r->id)) {
                        $candidateIds[] = (int) $r->id;
                    } elseif (is_array($r) && isset($r['id'])) {
                        $candidateIds[] = (int) $r['id'];
                    }
                }
            } catch (Throwable) {
                // Table not mounted or records not accessible
            }
        }

        if (empty($candidateIds)) {
            try {
                if (Schema::hasTable('packages')) {
                    $candidateIds = DB::table('packages')
                        ->pluck('id')
                        ->map(fn ($id) => (int) $id)
                        ->all();
                }
            } catch (Throwable) {
                // Fallback if packages table query fails
            }
        }

        if (! in_array($packageId, $candidateIds, true)) {
            $candidateIds[] = $packageId;
        }

        self::loadHealthData($candidateIds);

        return self::$memoizedHealth[self::tenantKey()][$packageId] ?? [
            'status' => 'غير مربوطة',
            'tooltip' => null,
        ];
    }

    public function pluginId(): string
    {
        return 'pos-integration';
    }

    /**
     * @return array<Column>
     */
    public function columns(): array
    {
        // If the combo tables do not exist (Schema::hasTable false), return no columns.
        if (! Schema::hasTable('packages') || ! Schema::hasTable('package_groups') || ! Schema::hasTable('package_items')) {
            return [];
        }

        $column = TextColumn::make('pos_status')
            ->label('POS')
            ->badge()
            ->color(fn (?string $state): ?string => match ($state) {
                'مربوطة' => 'success',
                'مكونات مربوطة' => 'info',
                'غير مربوطة' => 'danger',
                default => null,
            })
            ->state(function (mixed $record = null) use (&$column): ?string {
                $record ??= $column?->getRecord();
                if ($record === null) {
                    return null;
                }

                $id = $record instanceof Model ? $record->getKey() : ($record->id ?? null);
                if ($id === null) {
                    return null;
                }

                return self::getHealthData((int) $id, $column)['status'];
            })
            ->tooltip(function (mixed $record = null, ?string $state = null) use (&$column): ?string {
                $record ??= $column?->getRecord();
                if ($record === null) {
                    return null;
                }

                $id = $record instanceof Model ? $record->getKey() : ($record->id ?? null);
                if ($id === null) {
                    return null;
                }

                return self::getHealthData((int) $id, $column)['tooltip'];
            });

        return [$column];
    }

    /**
     * Reset the static memoization cache (useful between requests or tests).
     */
    /**
     * Package ids repeat across tenant databases, so the memo is partitioned per tenant.
     */
    private static function tenantKey(): string
    {
        return (string) (tenant()?->getTenantKey() ?? 'central');
    }

    /**
     * Batch-load and memoize health data for an array of package IDs.
     *
     * @param  array<int>  $packageIds
     */
    private static function loadHealthData(array $packageIds): void
    {
        $unloadedIds = array_values(array_filter(
            $packageIds,
            fn (int $id): bool => ! isset(self::$memoizedHealth[self::tenantKey()][$id])
        ));

        if (empty($unloadedIds)) {
            return;
        }

        // 1. Package-level mappings: check pos_line_mappings for line_type 'package'
        $packageLevelMappedIds = [];
        if (Schema::hasTable('pos_line_mappings')) {
            $packageLevelMappedIds = DB::table('pos_line_mappings')
                ->where('line_type', 'package')
                ->whereIn('line_reference_id', $unloadedIds)
                ->pluck('line_reference_id')
                ->map(fn ($id) => (int) $id)
                ->flip()
                ->all();
        }

        $packagesNeedingComponents = array_values(array_filter(
            $unloadedIds,
            fn (int $id): bool => ! isset($packageLevelMappedIds[$id])
        ));

        $itemsByPackage = [];
        $mappedProductIds = [];

        if (! empty($packagesNeedingComponents) && Schema::hasTable('package_groups') && Schema::hasTable('package_items')) {
            // NOTE: Reads package_groups and package_items tables read-only from the combo-packages
            // plugin via query builder (DB::table) without referencing ComboPackages classes.
            $items = DB::table('package_groups')
                ->join('package_items', 'package_items.package_group_id', '=', 'package_groups.id')
                ->leftJoin('products', 'products.id', '=', 'package_items.product_id')
                ->whereIn('package_groups.package_id', $packagesNeedingComponents)
                ->select([
                    'package_groups.package_id',
                    'package_items.product_id',
                    'products.name as product_name',
                    'products.name_ar as product_name_ar',
                ])
                ->get();

            foreach ($items as $item) {
                $pkgId = (int) $item->package_id;
                $itemsByPackage[$pkgId][] = $item;
            }

            $allProductIds = $items
                ->pluck('product_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            if (! empty($allProductIds) && Schema::hasTable('product_pos_mappings')) {
                $mappedProductIds = DB::table('product_pos_mappings')
                    ->whereIn('product_id', $allProductIds)
                    ->pluck('product_id')
                    ->map(fn ($id) => (int) $id)
                    ->flip()
                    ->all();
            }
        }

        foreach ($unloadedIds as $id) {
            if (isset($packageLevelMappedIds[$id])) {
                self::$memoizedHealth[self::tenantKey()][$id] = [
                    'status' => 'مربوطة',
                    'tooltip' => null,
                ];

                continue;
            }

            $packageItems = $itemsByPackage[$id] ?? [];
            if (empty($packageItems)) {
                self::$memoizedHealth[self::tenantKey()][$id] = [
                    'status' => 'غير مربوطة',
                    'tooltip' => null,
                ];

                continue;
            }

            $unmappedNames = [];
            $allMapped = true;

            foreach ($packageItems as $item) {
                $prodId = (int) $item->product_id;
                if (! isset($mappedProductIds[$prodId])) {
                    $allMapped = false;
                    $name = ! empty($item->product_name)
                        ? (string) $item->product_name
                        : (! empty($item->product_name_ar) ? (string) $item->product_name_ar : 'Product #'.$prodId);
                    $unmappedNames[$prodId] = $name;
                }
            }

            if ($allMapped) {
                self::$memoizedHealth[self::tenantKey()][$id] = [
                    'status' => 'مكونات مربوطة',
                    'tooltip' => null,
                ];
            } else {
                self::$memoizedHealth[self::tenantKey()][$id] = [
                    'status' => 'غير مربوطة',
                    'tooltip' => implode(', ', array_values($unmappedNames)),
                ];
            }
        }
    }
}
