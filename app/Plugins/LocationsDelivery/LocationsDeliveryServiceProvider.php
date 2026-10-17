<?php

declare(strict_types=1);

namespace App\Plugins\LocationsDelivery;

use Illuminate\Support\ServiceProvider;

final class LocationsDeliveryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind plugin services
    }

    public function boot(): void
    {
        if (class_exists(\App\Models\User::class)) {
            \App\Models\User::resolveRelationUsing('addresses', function (\App\Models\User $user) {
                return $user->hasMany(\App\Plugins\LocationsDelivery\src\Models\Address::class);
            });
        }

        if (file_exists(__DIR__.'/routes/tenant.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/tenant.php');
        }
    }
}
