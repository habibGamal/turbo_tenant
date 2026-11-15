<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Core\Plugins\Contracts\SetupChecklistItem;
use App\Core\Plugins\Services\PluginContributions;
use Filament\Widgets\Widget;
use Throwable;

final class StoreSetupChecklist extends Widget
{
    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.store-setup-checklist';

    public static function canView(): bool
    {
        $items = self::getApplicableItems();

        if (empty($items)) {
            return false;
        }

        foreach ($items as $item) {
            if (! $item['is_completed']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array{id: string, key: string, title: string, label: string, description: string, is_completed: bool, url: ?string, sort_order: int}>
     */
    public static function getApplicableItems(): array
    {
        /** @var PluginContributions $contributions */
        $contributions = app(PluginContributions::class);

        /** @var list<SetupChecklistItem> $items */
        $items = array_values(array_filter(
            $contributions->active('admin.setup-checklist'),
            fn ($item): bool => $item instanceof SetupChecklistItem,
        ));

        usort($items, fn (SetupChecklistItem $a, SetupChecklistItem $b): int => $a->sortOrder() <=> $b->sortOrder());

        return array_map(function (SetupChecklistItem $item): array {
            $isComplete = false;
            try {
                $isComplete = $item->isComplete();
            } catch (Throwable) {
                $isComplete = false;
            }

            $url = null;
            try {
                $url = $item->url();
            } catch (Throwable) {
                $url = null;
            }

            $description = $item->description();

            return [
                'id' => $item->key(),
                'key' => $item->key(),
                'title' => $item->label(),
                'label' => $item->label(),
                'description' => $description,
                'is_completed' => $isComplete,
                'url' => $url,
                'sort_order' => $item->sortOrder(),
            ];
        }, $items);
    }

    protected function getViewData(): array
    {
        $items = self::getApplicableItems();
        $total = count($items);
        $completed = count(array_filter($items, fn (array $i): bool => $i['is_completed']));
        $percentage = $total > 0 ? (int) round(($completed / $total) * 100) : 100;

        return [
            'items' => $items,
            'total' => $total,
            'completed' => $completed,
            'percentage' => $percentage,
        ];
    }
}
