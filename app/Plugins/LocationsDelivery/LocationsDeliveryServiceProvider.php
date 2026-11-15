<?php

declare(strict_types=1);

namespace App\Plugins\LocationsDelivery;

use App\Plugins\LocationsDelivery\src\Contracts\AddressBook;
use App\Plugins\LocationsDelivery\src\Contracts\DeliveryQuote;
use App\Plugins\LocationsDelivery\src\Models\Address;
use App\Plugins\LocationsDelivery\src\Policies\AddressPolicy;
use App\Plugins\LocationsDelivery\src\Services\EloquentAddressBook;
use App\Plugins\LocationsDelivery\src\Services\EloquentDeliveryQuote;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class LocationsDeliveryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DeliveryQuote::class, EloquentDeliveryQuote::class);
        $this->app->bind(AddressBook::class, EloquentAddressBook::class);

        $this->app->singleton(src\Filament\Checklist\ActiveBranchSetupChecklistItem::class);
        $this->app->singleton(src\Filament\Checklist\DeliveryAreaSetupChecklistItem::class);
        $this->app->tag([
            src\Filament\Checklist\ActiveBranchSetupChecklistItem::class,
            src\Filament\Checklist\DeliveryAreaSetupChecklistItem::class,
        ], 'admin.setup-checklist');
    }

    public function boot(): void
    {
        Gate::policy(Address::class, AddressPolicy::class);

        if (class_exists(\App\Models\User::class)) {
            \App\Models\User::resolveRelationUsing('addresses', function (\App\Models\User $user) {
                return $user->hasMany(Address::class);
            });
        }

        if (file_exists(__DIR__.'/routes/tenant.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/tenant.php');
        }
    }
}
