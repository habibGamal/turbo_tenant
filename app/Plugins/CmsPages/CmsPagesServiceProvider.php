<?php

declare(strict_types=1);

namespace App\Plugins\CmsPages;

use App\Plugins\CmsPages\src\Filament\Resources\Pages\PageResource;
use Illuminate\Support\ServiceProvider;

final class CmsPagesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register legacy class alias for backward compatibility
        if (! class_exists('App\Filament\Resources\Pages\PageResource', false)) {
            class_alias(PageResource::class, 'App\Filament\Resources\Pages\PageResource');
        }
    }

    public function boot(): void
    {
        if (file_exists(__DIR__.'/routes/tenant.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/tenant.php');
        }
    }
}
