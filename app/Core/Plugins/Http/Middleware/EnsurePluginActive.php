<?php

declare(strict_types=1);

namespace App\Core\Plugins\Http\Middleware;

use App\Core\Plugins\Services\PluginManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsurePluginActive
{
    public function __construct(
        private readonly PluginManager $pluginManager
    ) {}

    public function handle(Request $request, Closure $next, string $pluginId): Response
    {
        $tenant = tenant();

        if ($tenant !== null && ! $this->pluginManager->isPluginActive($pluginId, $tenant)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Feature not available',
            ], 404);
        }

        return $next($request);
    }
}
