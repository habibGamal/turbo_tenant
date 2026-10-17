<?php

declare(strict_types=1);

namespace App\Plugins\GuestCheckout\src\Http\Middleware;

use App\Core\Plugins\Services\PluginManager;
use Closure;
use Illuminate\Http\Request;

final class EnsureCheckoutAccessMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $tenant = function_exists('tenant') ? tenant() : null;
        $guestActive = $tenant
            ? app(PluginManager::class)->isPluginActive('guest-checkout', $tenant)
            : app(\App\Core\Plugins\Services\PluginRegistry::class)->has('guest-checkout');

        if (! auth()->check() && ! $guestActive) {
            if ($request->expectsJson() || $request->is('*/orders/place') || $request->is('orders/place')) {
                return response()->json([
                    'success' => false,
                    'errors' => [
                        'guest_data' => ['Guest checkout is not permitted for this store. Please log in.'],
                    ],
                ], 422);
            }

            session()->put('url.intended', route('checkout'));

            return redirect()->route('login')->with('warning', 'Please sign in or create an account to proceed with checkout.');
        }

        return $next($request);
    }
}
