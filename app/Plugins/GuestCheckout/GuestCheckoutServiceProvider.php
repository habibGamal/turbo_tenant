<?php

declare(strict_types=1);

namespace App\Plugins\GuestCheckout;

use App\Plugins\GuestCheckout\src\Models\GuestUser;
use App\Plugins\GuestCheckout\src\Services\GuestUserService;
use App\Plugins\OrderManagement\src\Models\Order;
use Illuminate\Support\ServiceProvider;

final class GuestCheckoutServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GuestUserService::class);
        $this->app->singleton('guest.user.service', GuestUserService::class);
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
