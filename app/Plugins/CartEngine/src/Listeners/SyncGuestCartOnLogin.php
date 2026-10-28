<?php

declare(strict_types=1);

namespace App\Plugins\CartEngine\src\Listeners;

use App\Core\Plugins\Services\PluginManager;
use App\Models\User;
use App\Plugins\CartEngine\src\Services\CartService;
use Illuminate\Auth\Events\Login;
use Throwable;

final class SyncGuestCartOnLogin
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly PluginManager $pluginManager,
    ) {}

    public function handle(Login $event): void
    {
        if (! function_exists('tenancy') || ! tenancy()->initialized) {
            return;
        }

        $tenant = function_exists('tenant') ? tenant() : null;
        if (! $tenant || ! $this->pluginManager->isPluginActive('cart-engine', $tenant)) {
            return;
        }

        if (! ($event->user instanceof User)) {
            return;
        }

        try {
            $this->cartService->syncGuestCartToUser($event->user);
        } catch (Throwable $e) {
            logger()->error('Cart sync error on login: '.$e->getMessage());
        }
    }
}
