<?php

declare(strict_types=1);

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\TenancyServiceProvider::class,
    App\Providers\Filament\AdminPanelProvider::class,
    App\Providers\Filament\CentralPanelProvider::class,
    App\Providers\PluginServiceProvider::class,
    App\Providers\OrderServiceProvider::class,
];
