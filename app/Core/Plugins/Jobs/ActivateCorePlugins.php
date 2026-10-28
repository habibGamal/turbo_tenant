<?php

declare(strict_types=1);

namespace App\Core\Plugins\Jobs;

use App\Core\Plugins\Services\PluginDependencyResolver;
use App\Core\Plugins\Services\PluginLifecycleService;
use App\Core\Plugins\Services\PluginRegistry;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Stancl\Tenancy\Contracts\TenantWithDatabase;

final class ActivateCorePlugins implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected TenantWithDatabase $tenant
    ) {}

    public function handle(
        PluginRegistry $registry,
        PluginDependencyResolver $resolver,
        PluginLifecycleService $lifecycle
    ): void {
        /** @var Tenant $tenant */
        $tenant = $this->tenant;

        $coreManifests = $registry->all()->filter(fn ($manifest) => $manifest->isCore);
        $sortedManifests = $resolver->sort($coreManifests);

        foreach ($sortedManifests as $manifest) {
            $lifecycle->activate($tenant, $manifest->id);
        }
    }
}
