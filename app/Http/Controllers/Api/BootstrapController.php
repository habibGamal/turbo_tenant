<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\Plugins\Contracts\BootstrapContributor;
use App\Core\Plugins\Services\PluginContributions;
use App\Core\Plugins\Services\PluginManager;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\CustomerResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BootstrapController extends Controller
{
    public function __construct(
        private readonly PluginManager $pluginManager,
        private readonly PluginContributions $pluginContributions,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $tenant = tenant();
        $user = $request->user();

        // 1. Resolve Active Plugins for this Tenant
        $activePlugins = $tenant ? $this->pluginManager->getActivePluginIds($tenant) : [];

        // 2. Resolve Dynamic Bootstrap Contributors
        $contributions = [
            'settings' => [],
            'cart_count' => 0,
            'query_data' => [],
        ];

        foreach ($this->pluginContributions->active('tenant.bootstrap.contributors') as $contributor) {
            if ($contributor instanceof BootstrapContributor) {
                $payload = $contributor->contribute($user);
                $contributions['query_data'] = array_merge($contributions['query_data'], $payload['query_data'] ?? []);
                unset($payload['query_data']);
                if (isset($payload['settings']) && is_array($payload['settings'])) {
                    $contributions['settings'] = array_merge($contributions['settings'], $payload['settings']);
                    unset($payload['settings']);
                }
                $contributions = array_merge($contributions, $payload);
            }
        }

        // 3. Resolve Theme
        $themeName = 'default';
        if ($tenant) {
            $themeName = $tenant->theme ?? 'default';
        }

        $tenantName = null;
        if ($tenant) {
            $nameAttr = $tenant->name ?? ($tenant->data['name'] ?? null);
            $tenantName = (is_string($nameAttr) && mb_trim($nameAttr) !== '') ? $nameAttr : (string) $tenant->id;
        }

        return response()->json([
            'tenant' => $tenant ? [
                'id' => (string) $tenant->id,
                'name' => $tenantName,
                'theme' => $themeName,
            ] : null,
            'user' => $user ? CustomerResource::make($user)->resolve() : null,
            'settings' => $contributions['settings'] ?? [],
            'active_plugins' => $activePlugins,
            'cart_count' => $contributions['cart_count'] ?? 0,
            'query_data' => $contributions['query_data'],
            'is_dev_mode' => config('app.env') === 'local',
        ]);
    }
}
