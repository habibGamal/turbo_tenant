<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\Plugins\Contracts\BootstrapContributor;
use App\Core\Plugins\Services\PluginManager;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\CustomerResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BootstrapController extends Controller
{
    public function __construct(
        private readonly PluginManager $pluginManager,
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
        ];

        /** @var iterable<BootstrapContributor> $contributors */
        $contributors = app()->tagged('tenant.bootstrap.contributors');
        foreach ($contributors as $contributor) {
            if ($this->pluginManager->isPluginActive($contributor->pluginId(), $tenant)) {
                $contributions = array_merge($contributions, $contributor->contribute($user));
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
        ]);
    }
}
