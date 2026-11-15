<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Plugins\Services\PluginRegistry;
use Illuminate\Console\Command;

final class PluginClearCommand extends Command
{
    protected $signature = 'plugin:clear';

    protected $description = 'Remove the cached plugin manifest file';

    public function handle(PluginRegistry $registry): int
    {
        $registry->clearCache();

        $this->info('Plugin cache cleared successfully.');

        return self::SUCCESS;
    }
}
