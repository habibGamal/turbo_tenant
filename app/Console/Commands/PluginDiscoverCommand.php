<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Plugins\Services\PluginRegistry;
use Illuminate\Console\Command;

final class PluginDiscoverCommand extends Command
{
    protected $signature = 'plugin:discover';

    protected $description = 'Discover all plugins from disk and synchronize with the central plugins table';

    public function handle(PluginRegistry $registry): int
    {
        $this->info('Discovering plugins from app/Plugins...');

        $registry->discover();
        $manifests = $registry->all();

        if ($manifests->isEmpty()) {
            $this->warn('No plugins found in app/Plugins.');

            return self::SUCCESS;
        }

        $registry->syncDatabase();

        $rows = $manifests->map(fn ($m) => [
            $m->id,
            $m->name,
            $m->version,
            $m->category->value,
            $m->isCore ? 'Yes' : 'No',
            implode(', ', $m->dependencies) ?: 'None',
        ])->values()->all();

        $this->table(
            ['ID', 'Name', 'Version', 'Category', 'Core', 'Dependencies'],
            $rows
        );

        $this->info("Successfully discovered and synchronized {$manifests->count()} plugin(s).");

        return self::SUCCESS;
    }
}
