<?php

declare(strict_types=1);

namespace App\Plugins\GuestCheckout;

use App\Plugins\GuestCheckout\src\Http\Response\GuestTrackingResponseContributor;
use App\Plugins\GuestCheckout\src\Http\Rules\GuestDataRulesContributor;
use App\Plugins\GuestCheckout\src\Models\GuestUser;
use App\Plugins\GuestCheckout\src\Resolvers\GuestDataCustomerResolver;
use App\Plugins\GuestCheckout\src\Services\GuestTrackingTokenService;
use App\Plugins\GuestCheckout\src\Services\GuestUserService;
use App\Plugins\OrderManagement\src\Models\Order;
use Illuminate\Support\ServiceProvider;

final class GuestCheckoutServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GuestUserService::class);
        $this->app->singleton(GuestTrackingTokenService::class);

        $this->app->singleton(GuestDataRulesContributor::class);
        $this->app->singleton(GuestDataCustomerResolver::class);
        $this->app->singleton(GuestTrackingResponseContributor::class);

        $this->app->tag([GuestDataRulesContributor::class], 'order.placement.rules');
        $this->app->tag([GuestDataCustomerResolver::class], 'order.customer.resolvers');
        $this->app->tag([GuestTrackingResponseContributor::class], 'order.placement.response');
    }

    public function boot(): void
    {
        $this->registerRelations();

        // 2. Load plugin routes
        if (file_exists(__DIR__.'/routes/tenant.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/tenant.php');
        }
    }

    private function registerRelations(): void
    {
        Order::resolveRelationUsing('guestUser', function (Order $order) {
            return $order->belongsTo(GuestUser::class, 'guest_user_id');
        });
    }
}
