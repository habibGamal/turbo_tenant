<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Core\Plugins\DTOs\PluginManifest;
use App\Core\Plugins\Enums\PluginCategory;
use App\Core\Plugins\Services\PluginDependencyResolver;
use App\Core\Plugins\Services\PluginLifecycleService;
use App\Core\Plugins\Services\PluginManager;
use App\Core\Plugins\Services\PluginRegistry;
use App\Models\Tenant;
use App\Models\TenantPlugin;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Throwable;

final class ManagePlugins extends Page
{
    /**
     * Search query string.
     */
    public string $search = '';

    /**
     * Active category filter.
     */
    public string $selectedCategory = 'all';

    /**
     * Active status filter ('all', 'installed', 'not_installed', 'core').
     */
    public string $selectedStatus = 'all';

    /**
     * Dependency resolution modal state.
     */
    public bool $showDependencyModal = false;

    public ?string $targetPluginId = null;

    /**
     * @var array<int, array{id: string, name: string}>
     */
    public array $pendingMissingDependencies = [];

    /**
     * Uninstall confirmation modal state.
     */
    public bool $showUninstallModal = false;

    public ?string $uninstallPluginId = null;

    public ?string $uninstallPluginName = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static ?string $navigationLabel = 'سوق الإضافات';

    protected static ?string $title = 'سوق الإضافات والخدمات';

    protected static ?string $slug = 'plugins';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.manage-plugins';

    /**
     * @return array<string, string>
     */
    public function getCategories(): array
    {
        return [
            'all' => 'الكل (All)',
            PluginCategory::FOUNDATION->value => 'الأساس (Foundation)',
            PluginCategory::COMMERCE->value => 'التجارة (Commerce)',
            PluginCategory::MARKETING->value => 'التسويق (Marketing)',
            PluginCategory::COMMUNICATION->value => 'الاتصالات (Communication)',
            PluginCategory::STOREFRONT->value => 'واجهة المتجر (Storefront)',
            PluginCategory::ADDON->value => 'إضافات عامة (Addons)',
        ];
    }

    /**
     * Get computed statistics about plugins.
     *
     * @return array{total: int, active: int, available: int, core: int}
     */
    public function getStatsProperty(): array
    {
        $tenant = $this->getCurrentTenant();
        if (! $tenant) {
            return ['total' => 0, 'active' => 0, 'available' => 0, 'core' => 0];
        }

        $registry = app(PluginRegistry::class);
        $pluginManager = app(PluginManager::class);

        $allManifests = $registry->all();
        $activeIds = $pluginManager->getActivePluginIds($tenant);

        $coreCount = $allManifests->filter(fn (PluginManifest $m): bool => $m->isCore)->count();
        $activeCount = count($activeIds);
        $totalCount = $allManifests->count();

        return [
            'total' => $totalCount,
            'active' => $activeCount,
            'available' => max(0, $totalCount - $activeCount),
            'core' => $coreCount,
        ];
    }

    /**
     * Get filtered list of all available plugins with their current state.
     *
     * @return Collection<string, array<string, mixed>>
     */
    public function getPluginsProperty(): Collection
    {
        $tenant = $this->getCurrentTenant();
        if (! $tenant) {
            return collect();
        }

        $registry = app(PluginRegistry::class);
        $pluginManager = app(PluginManager::class);
        $resolver = app(PluginDependencyResolver::class);

        $manifests = $registry->all();
        $activePluginIds = $pluginManager->getActivePluginIds($tenant);

        $tenantPlugins = TenantPlugin::query()
            ->where('tenant_id', (string) $tenant->id)
            ->get()
            ->keyBy('plugin_id');

        // Precompute reverse dependencies: which active plugins depend on plugin X?
        $dependentsMap = [];
        foreach ($activePluginIds as $activeId) {
            $manifest = $registry->get($activeId);
            if ($manifest !== null) {
                foreach ($manifest->dependencies as $depId) {
                    $dependentsMap[$depId][] = $manifest->name;
                }
            }
        }

        $items = collect();

        foreach ($manifests as $manifest) {
            $isCore = $manifest->isCore;
            $record = $tenantPlugins->get($manifest->id);
            $isActive = in_array($manifest->id, $activePluginIds, true);
            $isInstalled = $record !== null;
            $missingDeps = $resolver->getMissingDependencies($manifest, $activePluginIds);

            // Filter by search query
            if ($this->search !== '') {
                $term = mb_strtolower(mb_trim($this->search));
                $matchesName = str_contains(mb_strtolower($manifest->name), $term);
                $matchesDesc = str_contains(mb_strtolower($manifest->description ?? ''), $term);
                $matchesId = str_contains(mb_strtolower($manifest->id), $term);
                if (! $matchesName && ! $matchesDesc && ! $matchesId) {
                    continue;
                }
            }

            // Filter by category
            if ($this->selectedCategory !== 'all' && $manifest->category->value !== $this->selectedCategory) {
                continue;
            }

            // Filter by status
            if ($this->selectedStatus === 'installed' && ! $isActive) {
                continue;
            }
            if ($this->selectedStatus === 'not_installed' && $isActive) {
                continue;
            }
            if ($this->selectedStatus === 'core' && ! $isCore) {
                continue;
            }

            // Human readable names for missing dependencies
            $missingDepNames = array_map(function (string $depId) use ($registry): string {
                return $registry->get($depId)?->name ?? $depId;
            }, $missingDeps);

            // Detailed dependencies status
            $dependenciesDetail = array_map(function (string $depId) use ($registry, $activePluginIds): array {
                $depManifest = $registry->get($depId);

                return [
                    'id' => $depId,
                    'name' => $depManifest?->name ?? $depId,
                    'is_active' => in_array($depId, $activePluginIds, true),
                ];
            }, $manifest->dependencies);

            $items->put($manifest->id, [
                'manifest' => $manifest,
                'id' => $manifest->id,
                'name' => $manifest->name,
                'version' => $manifest->version,
                'category' => $manifest->category->value,
                'category_label' => $this->getCategories()[$manifest->category->value] ?? $manifest->category->value,
                'description' => $manifest->description,
                'author' => $manifest->author,
                'is_core' => $isCore,
                'dependencies' => $manifest->dependencies,
                'dependencies_detail' => $dependenciesDetail,
                'is_active' => $isActive,
                'is_installed' => $isInstalled,
                'status' => $record?->status->value ?? 'not_installed',
                'record' => $record,
                'missing_dependencies' => $missingDeps,
                'missing_dependency_names' => $missingDepNames,
                'active_dependents' => $dependentsMap[$manifest->id] ?? [],
            ]);
        }

        return $items;
    }

    /**
     * Reset all search and filtering criteria.
     */
    public function resetFilters(): void
    {
        $this->search = '';
        $this->selectedCategory = 'all';
        $this->selectedStatus = 'all';
    }

    /**
     * Install and activate a plugin, prompting if prerequisite dependencies are missing.
     */
    public function installPlugin(string $pluginId): void
    {
        $tenant = $this->getCurrentTenant();
        if (! $tenant) {
            Notification::make()
                ->title('خطأ: لا يوجد متجر نشط')
                ->danger()
                ->send();

            return;
        }

        $registry = app(PluginRegistry::class);
        $manifest = $registry->get($pluginId);
        if (! $manifest) {
            Notification::make()
                ->title('الإضافة غير موجودة في سجل النظام')
                ->danger()
                ->send();

            return;
        }

        $resolver = app(PluginDependencyResolver::class);
        $pluginManager = app(PluginManager::class);
        $activeIds = $pluginManager->getActivePluginIds($tenant);
        $missing = $resolver->getMissingDependencies($manifest, $activeIds);

        if (! empty($missing)) {
            $this->targetPluginId = $pluginId;
            $this->pendingMissingDependencies = array_map(
                fn (string $id): array => [
                    'id' => $id,
                    'name' => $registry->get($id)?->name ?? $id,
                ],
                $missing
            );
            $this->showDependencyModal = true;
            $this->dispatch('open-modal', id: 'dependency-modal');

            return;
        }

        try {
            $lifecycle = app(PluginLifecycleService::class);
            $lifecycle->activate($tenant, $pluginId);

            Notification::make()
                ->title("تم تثبيت وتفعيل \"{$manifest->name}\" بنجاح")
                ->success()
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->title('فشل تثبيت الإضافة')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Confirm recursive installation of missing dependencies and the target plugin.
     */
    public function confirmInstallWithDependencies(): void
    {
        if (! $this->targetPluginId) {
            return;
        }

        $tenant = $this->getCurrentTenant();
        if (! $tenant) {
            return;
        }

        $registry = app(PluginRegistry::class);
        $lifecycle = app(PluginLifecycleService::class);
        $resolver = app(PluginDependencyResolver::class);
        $pluginManager = app(PluginManager::class);

        try {
            $toInstall = $this->resolveAllDependenciesRecursive(
                $this->targetPluginId,
                $tenant,
                $registry,
                $resolver,
                $pluginManager
            );

            foreach ($toInstall as $depId) {
                $lifecycle->activate($tenant, $depId);
            }

            // Finally activate target plugin
            $lifecycle->activate($tenant, $this->targetPluginId);

            $targetName = $registry->get($this->targetPluginId)?->name ?? $this->targetPluginId;

            Notification::make()
                ->title("تم تثبيت وتفعيل \"{$targetName}\" وجميع تبعياتها بنجاح")
                ->success()
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->title('حدث خطأ أثناء تثبيت الإضافات')
                ->body($e->getMessage())
                ->danger()
                ->send();
        } finally {
            $this->closeDependencyModal();
        }
    }

    public function closeDependencyModal(): void
    {
        $this->showDependencyModal = false;
        $this->targetPluginId = null;
        $this->pendingMissingDependencies = [];
        $this->dispatch('close-modal', id: 'dependency-modal');
    }

    /**
     * Deactivate an active plugin safely (preserves DB tables and files).
     */
    public function deactivatePlugin(string $pluginId): void
    {
        $tenant = $this->getCurrentTenant();
        if (! $tenant) {
            return;
        }

        $registry = app(PluginRegistry::class);
        $manifest = $registry->get($pluginId);

        if ($manifest?->isCore) {
            Notification::make()
                ->title('لا يمكن تعطيل هذه الإضافة')
                ->body('هذه إضافة أساسية للنظام ولا يمكن تعطيلها.')
                ->warning()
                ->send();

            return;
        }

        try {
            $lifecycle = app(PluginLifecycleService::class);
            $lifecycle->deactivate($tenant, $pluginId);

            Notification::make()
                ->title("تم تعطيل \"{$manifest?->name}\" بنجاح")
                ->info()
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->title('فشل تعطيل الإضافة')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Prompt admin with modal before completely uninstalling plugin.
     */
    public function promptUninstall(string $pluginId): void
    {
        $tenant = $this->getCurrentTenant();
        if (! $tenant) {
            return;
        }

        $registry = app(PluginRegistry::class);
        $manifest = $registry->get($pluginId);

        if ($manifest?->isCore) {
            Notification::make()
                ->title('لا يمكن حذف هذه الإضافة')
                ->body('هذه إضافة أساسية للنظام ولا يمكن إلغاء تثبيتها.')
                ->warning()
                ->send();

            return;
        }

        $pluginManager = app(PluginManager::class);
        $activeIds = $pluginManager->getActivePluginIds($tenant);
        $dependentNames = [];

        foreach ($activeIds as $activeId) {
            if ($activeId === $pluginId) {
                continue;
            }
            $activeManifest = $registry->get($activeId);
            if ($activeManifest !== null && in_array($pluginId, $activeManifest->dependencies, true)) {
                $dependentNames[] = $activeManifest->name;
            }
        }

        if (! empty($dependentNames)) {
            $namesStr = implode('، ', $dependentNames);
            Notification::make()
                ->title('لا يمكن إلغاء التثبيت حالياً')
                ->body("الإضافات التالية تعتمد عليها: {$namesStr}. يرجى إلغاء تثبيتها أو تعطيلها أولاً.")
                ->warning()
                ->send();

            return;
        }

        $this->uninstallPluginId = $pluginId;
        $this->uninstallPluginName = $manifest?->name ?? $pluginId;
        $this->showUninstallModal = true;
        $this->dispatch('open-modal', id: 'uninstall-modal');
    }

    /**
     * Confirm complete uninstallation (rolls back migrations and purges tenant storage).
     */
    public function confirmUninstall(): void
    {
        if (! $this->uninstallPluginId) {
            return;
        }

        $tenant = $this->getCurrentTenant();
        if (! $tenant) {
            return;
        }

        try {
            $lifecycle = app(PluginLifecycleService::class);
            $lifecycle->uninstall($tenant, $this->uninstallPluginId);

            Notification::make()
                ->title("تم إلغاء تثبيت وحذف بيانات \"{$this->uninstallPluginName}\" بنجاح")
                ->danger()
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->title('فشل إلغاء التثبيت')
                ->body($e->getMessage())
                ->danger()
                ->send();
        } finally {
            $this->closeUninstallModal();
        }
    }

    public function closeUninstallModal(): void
    {
        $this->showUninstallModal = false;
        $this->uninstallPluginId = null;
        $this->uninstallPluginName = null;
        $this->dispatch('close-modal', id: 'uninstall-modal');
    }

    /**
     * Resolve all missing dependencies recursively in topological order.
     *
     * @return array<string>
     */
    private function resolveAllDependenciesRecursive(
        string $pluginId,
        Tenant $tenant,
        PluginRegistry $registry,
        PluginDependencyResolver $resolver,
        PluginManager $pluginManager
    ): array {
        $activeIds = $pluginManager->getActivePluginIds($tenant);
        $targetManifest = $registry->get($pluginId);
        if (! $targetManifest) {
            return [];
        }

        $neededManifests = collect();
        $this->collectMissingManifests($targetManifest, $activeIds, $registry, $resolver, $neededManifests);

        $sorted = $resolver->sort($neededManifests);

        return array_keys($sorted);
    }

    /**
     * @param  array<string>  $activeIds
     * @param  Collection<string, PluginManifest>  $needed
     */
    private function collectMissingManifests(
        PluginManifest $manifest,
        array $activeIds,
        PluginRegistry $registry,
        PluginDependencyResolver $resolver,
        Collection $needed
    ): void {
        $missing = $resolver->getMissingDependencies($manifest, $activeIds);

        foreach ($missing as $depId) {
            if ($needed->has($depId)) {
                continue;
            }

            $depManifest = $registry->get($depId);
            if ($depManifest !== null) {
                $needed->put($depId, $depManifest);
                $this->collectMissingManifests($depManifest, $activeIds, $registry, $resolver, $needed);
            }
        }
    }

    private function getCurrentTenant(): ?Tenant
    {
        return function_exists('tenant') ? tenant() : null;
    }
}
