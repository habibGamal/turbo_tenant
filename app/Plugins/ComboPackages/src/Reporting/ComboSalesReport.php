<?php

declare(strict_types=1);

namespace App\Plugins\ComboPackages\src\Reporting;

use App\Plugins\ComboPackages\src\Models\Package;
use App\Plugins\OrderManagement\src\Enums\OrderStatus;
use App\Plugins\OrderManagement\src\Enums\PaymentStatus;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Fluent;

final class ComboSalesReport
{
    /**
     * Compute sales statistics for combos in the given date range.
     *
     * @return array{
     *     combos_sold: int,
     *     units: int,
     *     combo_revenue: float,
     *     revenue: float,
     *     average_price: float,
     *     average_combo_price: float,
     *     all_order_revenue: float,
     *     combo_share: float,
     *     combo_share_percentage: float
     * }
     */
    public function getStats(
        CarbonInterface|DateTimeInterface|string|null $startDate = null,
        CarbonInterface|DateTimeInterface|string|null $endDate = null,
    ): array {
        [$start, $end] = $this->resolveDateRange($startDate, $endDate);

        $comboQuery = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.line_type', 'package')
            ->where('orders.status', '!=', OrderStatus::CANCELLED->value)
            ->where(function ($query): void {
                $query->whereNull('orders.payment_status')
                    ->orWhere('orders.payment_status', '!=', PaymentStatus::FAILED->value);
            });

        if ($start !== null) {
            $comboQuery->where('orders.created_at', '>=', $start);
        }
        if ($end !== null) {
            $comboQuery->where('orders.created_at', '<=', $end);
        }

        $comboAggregates = $comboQuery
            ->selectRaw('COALESCE(SUM(order_items.quantity), 0) as combos_sold')
            ->selectRaw('COALESCE(SUM(order_items.total), 0) as combo_revenue')
            ->first();

        $combosSold = (int) round((float) ($comboAggregates?->combos_sold ?? 0.0));
        $comboRevenue = round((float) ($comboAggregates?->combo_revenue ?? 0.0), 2);
        $averagePrice = $combosSold > 0 ? round($comboRevenue / $combosSold, 2) : 0.0;

        $allOrdersQuery = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', '!=', OrderStatus::CANCELLED->value)
            ->where(function ($query): void {
                $query->whereNull('orders.payment_status')
                    ->orWhere('orders.payment_status', '!=', PaymentStatus::FAILED->value);
            });

        if ($start !== null) {
            $allOrdersQuery->where('orders.created_at', '>=', $start);
        }
        if ($end !== null) {
            $allOrdersQuery->where('orders.created_at', '<=', $end);
        }

        $allOrderRevenue = (float) $allOrdersQuery->sum('order_items.total');
        $allOrderRevenue = round($allOrderRevenue, 2);

        $comboShare = $allOrderRevenue > 0.0 ? round($comboRevenue / $allOrderRevenue, 4) : 0.0;
        $comboSharePct = $allOrderRevenue > 0.0 ? round(($comboRevenue / $allOrderRevenue) * 100.0, 2) : 0.0;

        return [
            'combos_sold' => $combosSold,
            'units' => $combosSold,
            'combo_revenue' => $comboRevenue,
            'revenue' => $comboRevenue,
            'average_price' => $averagePrice,
            'average_combo_price' => $averagePrice,
            'all_order_revenue' => $allOrderRevenue,
            'combo_share' => $comboShare,
            'combo_share_percentage' => $comboSharePct,
        ];
    }

    /**
     * Alias for getStats.
     *
     * @return array<string, mixed>
     */
    public function stats(
        CarbonInterface|DateTimeInterface|string|null $startDate = null,
        CarbonInterface|DateTimeInterface|string|null $endDate = null,
    ): array {
        return $this->getStats($startDate, $endDate);
    }

    /**
     * Compute per-package units and revenue in the date range, sorted by revenue descending.
     *
     * @return Collection<int, Fluent>
     */
    public function getPackageSales(
        CarbonInterface|DateTimeInterface|string|null $startDate = null,
        CarbonInterface|DateTimeInterface|string|null $endDate = null,
    ): Collection {
        [$start, $end] = $this->resolveDateRange($startDate, $endDate);

        $query = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.line_type', 'package')
            ->where('orders.status', '!=', OrderStatus::CANCELLED->value)
            ->where(function ($q): void {
                $q->whereNull('orders.payment_status')
                    ->orWhere('orders.payment_status', '!=', PaymentStatus::FAILED->value);
            });

        if ($start !== null) {
            $query->where('orders.created_at', '>=', $start);
        }
        if ($end !== null) {
            $query->where('orders.created_at', '<=', $end);
        }

        $rows = $query
            ->select([
                'order_items.line_reference_id as package_id',
                DB::raw('MAX(order_items.product_name) as snapshotted_name'),
                DB::raw('MAX(order_items.product_name_ar) as snapshotted_name_ar'),
                DB::raw('COALESCE(SUM(order_items.quantity), 0) as units'),
                DB::raw('COALESCE(SUM(order_items.total), 0) as revenue'),
            ])
            ->groupBy('order_items.line_reference_id')
            ->orderByDesc('revenue')
            ->get();

        $packageIds = $rows->pluck('package_id')->filter()->unique()->all();
        $packages = ! empty($packageIds)
            ? Package::withTrashed()->whereIn('id', $packageIds)->get()->keyBy('id')
            : collect();

        return $rows->map(function ($row) use ($packages): Fluent {
            $pkg = $packages->get((int) $row->package_id);
            $name = $pkg?->name ?? $row->snapshotted_name ?? 'Package #'.((string) $row->package_id);
            $nameAr = $pkg?->name_ar ?? $row->snapshotted_name_ar;
            $units = (int) round((float) $row->units);
            $revenue = round((float) $row->revenue, 2);
            $averagePrice = $units > 0 ? round($revenue / $units, 2) : 0.0;

            return new Fluent([
                'package_id' => (int) $row->package_id,
                'id' => (int) $row->package_id,
                'package_name' => $name,
                'name' => $name,
                'package_name_ar' => $nameAr,
                'name_ar' => $nameAr,
                'units' => $units,
                'quantity' => $units,
                'revenue' => $revenue,
                'total' => $revenue,
                'average_price' => $averagePrice,
            ]);
        });
    }

    /**
     * Alias for getPackageSales.
     *
     * @return Collection<int, Fluent>
     */
    public function packageSales(
        CarbonInterface|DateTimeInterface|string|null $startDate = null,
        CarbonInterface|DateTimeInterface|string|null $endDate = null,
    ): Collection {
        return $this->getPackageSales($startDate, $endDate);
    }

    /**
     * Option popularity for a chosen package: group -> option -> pick count.
     * Option is product_name plus variant_name (if present).
     *
     * @return array<string, array<string, int>>
     */
    public function getOptionPopularity(
        int $packageId,
        CarbonInterface|DateTimeInterface|string|null $startDate = null,
        CarbonInterface|DateTimeInterface|string|null $endDate = null,
    ): array {
        [$start, $end] = $this->resolveDateRange($startDate, $endDate);

        $query = DB::table('order_item_components')
            ->join('order_items', 'order_items.id', '=', 'order_item_components.order_item_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.line_type', 'package')
            ->where('order_items.line_reference_id', $packageId)
            ->where('orders.status', '!=', OrderStatus::CANCELLED->value)
            ->where(function ($q): void {
                $q->whereNull('orders.payment_status')
                    ->orWhere('orders.payment_status', '!=', PaymentStatus::FAILED->value);
            });

        if ($start !== null) {
            $query->where('orders.created_at', '>=', $start);
        }
        if ($end !== null) {
            $query->where('orders.created_at', '<=', $end);
        }

        $rows = $query
            ->select([
                'order_item_components.group_name',
                'order_item_components.product_name',
                'order_item_components.variant_name',
                // Component quantity is per combo; multiply by the line quantity so 3 identical combos count 3 times.
                DB::raw('COALESCE(SUM(order_item_components.quantity * order_items.quantity), 0) as pick_count'),
            ])
            ->groupBy(
                'order_item_components.group_name',
                'order_item_components.product_name',
                'order_item_components.variant_name'
            )
            ->orderBy('order_item_components.group_name')
            ->orderByDesc('pick_count')
            ->get();

        $result = [];

        foreach ($rows as $row) {
            $group = (string) ($row->group_name ?? 'Default');
            $product = (string) $row->product_name;
            $variant = $row->variant_name !== null && mb_trim((string) $row->variant_name) !== ''
                ? mb_trim((string) $row->variant_name)
                : null;

            $optionName = $variant !== null ? "{$product} ({$variant})" : $product;
            $count = (int) round((float) $row->pick_count);

            if (! isset($result[$group])) {
                $result[$group] = [];
            }

            $result[$group][$optionName] = $count;
        }

        return $result;
    }

    /**
     * Resolve start and end dates from mixed inputs. Defaults to last 30 days.
     *
     * @return array{0: Carbon|null, 1: Carbon|null}
     */
    private function resolveDateRange(
        CarbonInterface|DateTimeInterface|string|null $startDate,
        CarbonInterface|DateTimeInterface|string|null $endDate,
    ): array {
        $start = match (true) {
            $startDate instanceof CarbonInterface => Carbon::instance($startDate)->startOfDay(),
            $startDate instanceof DateTimeInterface => Carbon::instance($startDate)->startOfDay(),
            is_string($startDate) && mb_trim($startDate) !== '' => Carbon::parse($startDate)->startOfDay(),
            default => now()->subDays(30)->startOfDay(),
        };

        $end = match (true) {
            $endDate instanceof CarbonInterface => Carbon::instance($endDate)->endOfDay(),
            $endDate instanceof DateTimeInterface => Carbon::instance($endDate)->endOfDay(),
            is_string($endDate) && mb_trim($endDate) !== '' => Carbon::parse($endDate)->endOfDay(),
            default => now()->endOfDay(),
        };

        return [$start, $end];
    }
}
