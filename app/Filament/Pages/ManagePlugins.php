<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Core\Plugins\DTOs\PluginManifest;
use App\Core\Plugins\Enums\PluginCategory;
use App\Core\Plugins\Enums\PluginStatus;
use App\Core\Plugins\Services\PluginDependencyResolver;
use App\Core\Plugins\Services\PluginLifecycleService;
use App\Core\Plugins\Services\PluginManager;
use App\Core\Plugins\Services\PluginRegistry;
use App\Filament\Widgets\PluginStatsOverview;
use App\Models\Tenant;
use App\Models\TenantPlugin;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Resources\Concerns\HasTabs;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\ArrayRecord;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Throwable;
use UnitEnum;

final class ManagePlugins extends Page implements HasTable
{
    use HasTabs;
    use InteractsWithTable;

    #[Url(as: 'tab')]
    public ?string $activeTab = null;

    /**
     * @var array<string, mixed>|null
     */
    #[Url(as: 'filters')]
    public ?array $tableFilters = null;

    /**
     * @var ?string
     */
    #[Url(as: 'search')]
    public $tableSearch = '';

    #[Url(as: 'sort')]
    public ?string $tableSort = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static string|UnitEnum|null $navigationGroup = 'الإعدادات والنظام';

    protected static ?string $navigationLabel = 'سوق الإضافات';

    protected static ?string $title = 'سوق الإضافات والخدمات';

    protected static ?string $slug = 'plugins';

    protected static ?int $navigationSort = 2;

    /**
     * @var Collection<string, array<string, mixed>>|null
     */
    private ?Collection $cachedAllPluginRecords = null;

    /**
     * @var array{total: int, active: int, available: int, core: int}|null
     */
    private ?array $cachedPluginCounts = null;

    /**
     * @return array{total: int, active: int, available: int, core: int}
     */
    public static function resolvePluginCounts(?Tenant $tenant = null): array
    {
        $tenant ??= function_exists('tenant') ? tenant() : null;
        if (! $tenant) {
            return ['total' => 0, 'active' => 0, 'available' => 0, 'core' => 0];
        }

        $registry = app(PluginRegistry::class);
        $pluginManager = app(PluginManager::class);

        $allManifests = $registry->all();
        $activeIds = $pluginManager->getActivePluginIds($tenant);

        $totalCount = $allManifests->count();
        $activeCount = count($activeIds);
        $coreCount = $allManifests->filter(fn (PluginManifest $manifest): bool => $manifest->isCore)->count();

        return [
            'total' => $totalCount,
            'active' => $activeCount,
            'available' => max(0, $totalCount - $activeCount),
            'core' => $coreCount,
        ];
    }

    public function mount(): void
    {
        $this->loadDefaultActiveTab();
    }

    public function updatedActiveTab(): void
    {
        $this->resetPage();
        $this->cachedDefaultTableColumnState = null;
        $this->applyTableColumnManager();
        $this->flushCachedTableRecords();
    }

    public function getHeaderWidgetsColumns(): int
    {
        return 1;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getTabsContentComponent(),
                EmbeddedTable::make(),
            ]);
    }

    public function getDefaultActiveTab(): string
    {
        return 'all';
    }

    /**
     * @return array<string|int, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('الكل')
                ->icon('heroicon-m-squares-2x2')
                ->badge(fn (): int => $this->getPluginCounts()['total'])
                ->badgeColor('gray'),

            'installed' => Tab::make('المفعلة')
                ->icon('heroicon-m-check-circle')
                ->badge(fn (): int => $this->getPluginCounts()['active'])
                ->badgeColor('success'),

            'not_installed' => Tab::make('متاحة للتثبيت')
                ->icon('heroicon-m-arrow-down-tray')
                ->badge(fn (): int => $this->getPluginCounts()['available'])
                ->badgeColor('info'),

            'core' => Tab::make('الأساسية (Core)')
                ->icon('heroicon-m-lock-closed')
                ->badge(fn (): int => $this->getPluginCounts()['core'])
                ->badgeColor('warning'),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(function (
                ?string $search,
                ?array $filters,
                ?string $sortColumn,
                ?string $sortDirection,
                int $page,
                int|string $recordsPerPage,
            ): LengthAwarePaginator {
                $filtered = $this->filterAndSortRecords($search, $filters, $sortColumn, $sortDirection);
                $perPage = max(1, (int) $recordsPerPage);

                return new LengthAwarePaginator(
                    items: $filtered->forPage($page, $perPage),
                    total: $filtered->count(),
                    perPage: $perPage,
                    currentPage: $page,
                );
            })
            ->columns([
                TextColumn::make('name')
                    ->label('الإضافة')
                    ->icon(fn (array $record): string => match ($record['category']) {
                        'foundation' => 'heroicon-o-cube',
                        'commerce' => 'heroicon-o-shopping-bag',
                        'marketing' => 'heroicon-o-megaphone',
                        'communication' => 'heroicon-o-chat-bubble-left-right',
                        'storefront' => 'heroicon-o-building-storefront',
                        default => 'heroicon-o-puzzle-piece',
                    })
                    ->iconColor(fn (array $record): string => $record['is_active'] ? 'primary' : 'gray')
                    ->weight(FontWeight::Bold)
                    ->description(fn (array $record): string => $record['description'] !== '' ? $record['description'] : 'لا يوجد وصف متوفر لهذه الإضافة.')
                    ->wrap()
                    ->sortable(),

                TextColumn::make('id')
                    ->label('المعرف')
                    ->fontFamily(FontFamily::Mono)
                    ->size(TextSize::ExtraSmall)
                    ->color('gray')
                    ->copyable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('category_label')
                    ->label('التصنيف')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                TextColumn::make('version')
                    ->label('الإصدار')
                    ->formatStateUsing(fn (string $state): string => "v{$state}")
                    ->badge()
                    ->color('gray')
                    ->description(fn (array $record): ?string => filled($record['author']) ? (string) $record['author'] : null)
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('dependencies')
                    ->label('المتطلبات')
                    ->badge()
                    ->wrap()
                    ->placeholder('—')
                    ->formatStateUsing(fn (string $state, array $record): string => $record['dependencies_map'][$state]['name'] ?? $state)
                    ->color(fn (string $state, array $record): string => ($record['dependencies_map'][$state]['is_active'] ?? false) ? 'success' : 'danger')
                    ->icon(fn (string $state, array $record): string => ($record['dependencies_map'][$state]['is_active'] ?? false) ? 'heroicon-m-check' : 'heroicon-m-x-mark'),

                TextColumn::make('display_status')
                    ->label('الحالة')
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'core' => 'أساسي',
                        'active' => 'مفعل',
                        'deactivated' => 'معطل',
                        default => 'غير مثبت',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'core' => 'warning',
                        'active' => 'success',
                        'deactivated' => 'warning',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'core' => 'heroicon-m-lock-closed',
                        'active' => 'heroicon-m-check-circle',
                        'deactivated' => 'heroicon-m-pause-circle',
                        default => 'heroicon-m-arrow-down-tray',
                    }),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label('التصنيف')
                    ->placeholder('الكل (All)')
                    ->options($this->getCategoryOptions()),
            ])
            ->recordActions([
                Action::make('core_locked')
                    ->label('مثبت (أساسي)')
                    ->icon('heroicon-m-lock-closed')
                    ->color('gray')
                    ->size(Size::Small)
                    ->button()
                    ->disabled()
                    ->visible(fn (array $record): bool => (bool) $record['is_active'] && (bool) $record['is_core']),

                Action::make('install')
                    ->label(fn (array $record): string => $record['status'] === 'deactivated' ? 'تفعيل' : 'تثبيت وتفعيل')
                    ->icon(fn (array $record): string => $record['status'] === 'deactivated' ? 'heroicon-m-play' : 'heroicon-m-arrow-down-tray')
                    ->color(fn (array $record): string => $record['status'] === 'deactivated' ? 'success' : 'primary')
                    ->size(Size::Small)
                    ->button()
                    ->visible(fn (array $record): bool => ! $record['is_active'])
                    ->requiresConfirmation(fn (array $record): bool => ! empty($record['missing_dependencies']))
                    ->modal(fn (array $record): bool => ! empty($record['missing_dependencies']))
                    ->modalIcon(Heroicon::OutlinedPuzzlePiece)
                    ->modalIconColor('warning')
                    ->modalHeading('متطلبات التثبيت الإضافية')
                    ->modalDescription(function (array $record): string {
                        $deps = $record['recursive_missing_dependency_names'];
                        $list = ! empty($deps) ? implode(' ← ', $deps) : implode(' ← ', $record['missing_dependencies']);

                        return "سيقوم النظام بتثبيت وتفعيل الإضافات المطلوبة تلقائياً بالترتيب الصحيح قبل تفعيل \"{$record['name']}\": {$list}";
                    })
                    ->modalSubmitActionLabel('تثبيت وتفعيل الكل')
                    ->action(fn (array $record) => $this->handleInstallPlugin((string) $record['id'])),

                Action::make('deactivate')
                    ->label('تعطيل')
                    ->icon('heroicon-m-pause')
                    ->color('warning')
                    ->size(Size::Small)
                    ->button()
                    ->outlined()
                    ->visible(fn (array $record): bool => (bool) $record['is_active'] && ! $record['is_core'])
                    ->action(fn (array $record) => $this->handleDeactivatePlugin((string) $record['id'])),

                Action::make('uninstall')
                    ->label('إلغاء التثبيت')
                    ->icon('heroicon-m-trash')
                    ->color('danger')
                    ->size(Size::Small)
                    ->button()
                    ->outlined()
                    ->visible(fn (array $record): bool => ! $record['is_core'] && ((bool) $record['is_active'] || $record['status'] === 'deactivated'))
                    ->requiresConfirmation(fn (array $record): bool => empty($record['active_dependents']))
                    ->modal(fn (array $record): bool => empty($record['active_dependents']))
                    ->modalIcon(Heroicon::OutlinedTrash)
                    ->modalIconColor('danger')
                    ->modalHeading(fn (array $record): string => "تأكيد إلغاء تثبيت \"{$record['name']}\" وحذف البيانات")
                    ->modalDescription('تحذير: سيتم التراجع عن جميع جداول قاعدة البيانات وحذف مجلدات التخزين الخاصة بهذه الإضافة لمتجرك نهائياً. لا يمكن التراجع عن هذا الإجراء!')
                    ->modalSubmitActionLabel('نعم، إلغاء التثبيت وحذف البيانات')
                    ->action(fn (array $record) => $this->handleUninstallPlugin((string) $record['id'])),
            ])
            ->searchable()
            ->searchPlaceholder('ابحث باسم الإضافة أو الوصف أو المعرف...')
            ->defaultSort('name', 'asc')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->emptyStateIcon(Heroicon::OutlinedPuzzlePiece)
            ->emptyStateHeading('لا توجد إضافات تطابق معايير البحث')
            ->emptyStateDescription('حاول تعديل كلمة البحث أو تغيير الفلتر المختار.');
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PluginStatsOverview::class,
        ];
    }

    /**
     * Resolve a plugin record across tabs and pagination pages by its string ID.
     *
     * @return array<string, mixed>|null
     */
    protected function resolveTableRecord(?string $key): ?array
    {
        if ($key === null) {
            return null;
        }

        $record = $this->buildAllPluginRecords()->get($key);
        if ($record === null) {
            return null;
        }

        $record[ArrayRecord::getKeyName()] = $key;

        return $record;
    }

    /**
     * @return array{total: int, active: int, available: int, core: int}
     */
    private function getPluginCounts(): array
    {
        return $this->cachedPluginCounts ??= self::resolvePluginCounts($this->getCurrentTenant());
    }

    /**
     * @return array<string, string>
     */
    private function getCategoryOptions(): array
    {
        return [
            PluginCategory::FOUNDATION->value => 'الأساس (Foundation)',
            PluginCategory::COMMERCE->value => 'التجارة (Commerce)',
            PluginCategory::MARKETING->value => 'التسويق (Marketing)',
            PluginCategory::COMMUNICATION->value => 'الاتصالات (Communication)',
            PluginCategory::STOREFRONT->value => 'واجهة المتجر (Storefront)',
            PluginCategory::ADDON->value => 'إضافات عامة (Addons)',
        ];
    }

    /**
     * @return Collection<string, array<string, mixed>>
     */
    private function buildAllPluginRecords(): Collection
    {
        if ($this->cachedAllPluginRecords !== null) {
            return $this->cachedAllPluginRecords;
        }

        $tenant = $this->getCurrentTenant();
        if (! $tenant) {
            return $this->cachedAllPluginRecords = collect();
        }

        $registry = app(PluginRegistry::class);
        $pluginManager = app(PluginManager::class);
        $resolver = app(PluginDependencyResolver::class);

        $manifests = $registry->all();
        $activePluginIds = $pluginManager->getActivePluginIds($tenant);
        $categories = $this->getCategoryOptions();

        $tenantPlugins = TenantPlugin::query()
            ->where('tenant_id', (string) $tenant->id)
            ->get()
            ->keyBy('plugin_id');

        $dependentsMap = [];
        foreach ($activePluginIds as $activeId) {
            $activeManifest = $manifests->get($activeId);
            if ($activeManifest !== null) {
                foreach ($activeManifest->dependencies as $depId) {
                    $dependentsMap[$depId][] = $activeManifest->name;
                }
            }
        }

        $items = collect();

        foreach ($manifests as $manifest) {
            $record = $tenantPlugins->get($manifest->id);
            $isCore = $manifest->isCore;
            $isActive = in_array($manifest->id, $activePluginIds, true);
            $rawStatus = $record?->getAttribute('status');
            $status = $rawStatus instanceof PluginStatus
                ? $rawStatus->value
                : (is_string($rawStatus) ? $rawStatus : 'not_installed');
            $missingDeps = $resolver->getMissingDependencies($manifest, $activePluginIds);

            $displayStatus = match (true) {
                $isCore => 'core',
                $isActive => 'active',
                $status === 'deactivated' => 'deactivated',
                default => 'not_installed',
            };

            $dependenciesMap = [];
            foreach ($manifest->dependencies as $depId) {
                $depManifest = $manifests->get($depId);
                $dependenciesMap[$depId] = [
                    'name' => $depManifest !== null ? $depManifest->name : $depId,
                    'is_active' => in_array($depId, $activePluginIds, true),
                ];
            }

            $recursiveMissingIds = empty($missingDeps)
                ? []
                : $this->resolveAllDependenciesRecursive($manifest->id, $activePluginIds, $registry, $resolver);

            $recursiveMissingNames = array_map(
                static function (string $depId) use ($manifests): string {
                    $depManifest = $manifests->get($depId);

                    return $depManifest !== null ? $depManifest->name : $depId;
                },
                $recursiveMissingIds,
            );

            $items->put($manifest->id, [
                'id' => $manifest->id,
                'name' => $manifest->name,
                'version' => $manifest->version,
                'category' => $manifest->category->value,
                'category_label' => $categories[$manifest->category->value] ?? $manifest->category->value,
                'description' => (string) ($manifest->description ?? ''),
                'author' => $manifest->author,
                'is_core' => $isCore,
                'is_active' => $isActive,
                'is_installed' => $record !== null,
                'status' => $status,
                'display_status' => $displayStatus,
                'dependencies' => array_values($manifest->dependencies),
                'dependencies_map' => $dependenciesMap,
                'missing_dependencies' => array_values($missingDeps),
                'recursive_missing_dependency_names' => array_values($recursiveMissingNames),
                'active_dependents' => $dependentsMap[$manifest->id] ?? [],
            ]);
        }

        return $this->cachedAllPluginRecords = $items;
    }

    /**
     * @param  array<string, mixed>|null  $filters
     * @return Collection<string, array<string, mixed>>
     */
    private function filterAndSortRecords(
        ?string $search,
        ?array $filters,
        ?string $sortColumn,
        ?string $sortDirection,
    ): Collection {
        $items = $this->buildAllPluginRecords();

        $items = match ($this->activeTab) {
            'installed' => $items->filter(fn (array $record): bool => (bool) $record['is_active']),
            'not_installed' => $items->filter(fn (array $record): bool => ! $record['is_active']),
            'core' => $items->filter(fn (array $record): bool => (bool) $record['is_core']),
            default => $items,
        };

        $category = $filters['category']['value'] ?? null;
        if (filled($category)) {
            $items = $items->filter(fn (array $record): bool => $record['category'] === $category);
        }

        if (filled($search)) {
            $term = mb_strtolower(mb_trim($search));
            if ($term !== '') {
                $items = $items->filter(
                    fn (array $record): bool => str_contains(mb_strtolower((string) $record['name']), $term)
                        || str_contains(mb_strtolower((string) $record['description']), $term)
                        || str_contains(mb_strtolower((string) $record['id']), $term),
                );
            }
        }

        if (filled($sortColumn)) {
            $items = $items->sortBy(
                $sortColumn,
                SORT_NATURAL | SORT_FLAG_CASE,
                $sortDirection === 'desc',
            );
        }

        return $items;
    }

    private function handleInstallPlugin(string $pluginId): void
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
        $lifecycle = app(PluginLifecycleService::class);
        $activeIds = $pluginManager->getActivePluginIds($tenant);

        try {
            $toInstall = $this->resolveAllDependenciesRecursive($pluginId, $activeIds, $registry, $resolver);

            foreach ($toInstall as $depId) {
                $lifecycle->activate($tenant, $depId);
            }

            $lifecycle->activate($tenant, $pluginId);

            $title = empty($toInstall)
                ? "تم تثبيت وتفعيل \"{$manifest->name}\" بنجاح"
                : "تم تثبيت وتفعيل \"{$manifest->name}\" وجميع تبعياتها بنجاح";

            Notification::make()
                ->title($title)
                ->success()
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->title('فشل تثبيت الإضافة')
                ->body($e->getMessage())
                ->danger()
                ->send();
        } finally {
            $this->refreshPluginState();
        }
    }

    private function handleDeactivatePlugin(string $pluginId): void
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

        $pluginManager = app(PluginManager::class);
        $dependentNames = $this->getActiveDependentNames($pluginId, $registry, $pluginManager->getActivePluginIds($tenant));

        if (! empty($dependentNames)) {
            $namesStr = implode('، ', $dependentNames);
            Notification::make()
                ->title('لا يمكن تعطيل الإضافة حالياً')
                ->body("الإضافات التالية تعتمد عليها: {$namesStr}. يرجى تعطيلها أو إلغاء تثبيتها أولاً.")
                ->warning()
                ->send();

            return;
        }

        try {
            app(PluginLifecycleService::class)->deactivate($tenant, $pluginId);

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
        } finally {
            $this->refreshPluginState();
        }
    }

    private function handleUninstallPlugin(string $pluginId): void
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
        $dependentNames = $this->getActiveDependentNames($pluginId, $registry, $pluginManager->getActivePluginIds($tenant));

        if (! empty($dependentNames)) {
            $namesStr = implode('، ', $dependentNames);
            Notification::make()
                ->title('لا يمكن إلغاء التثبيت حالياً')
                ->body("الإضافات التالية تعتمد عليها: {$namesStr}. يرجى إلغاء تثبيتها أو تعطيلها أولاً.")
                ->warning()
                ->send();

            return;
        }

        $pluginName = $manifest !== null ? $manifest->name : $pluginId;

        try {
            app(PluginLifecycleService::class)->uninstall($tenant, $pluginId);

            Notification::make()
                ->title("تم إلغاء تثبيت وحذف بيانات \"{$pluginName}\" بنجاح")
                ->danger()
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->title('فشل إلغاء التثبيت')
                ->body($e->getMessage())
                ->danger()
                ->send();
        } finally {
            $this->refreshPluginState();
        }
    }

    /**
     * @param  array<string>  $activeIds
     * @return list<string>
     */
    private function getActiveDependentNames(
        string $pluginId,
        PluginRegistry $registry,
        array $activeIds,
    ): array {
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

        return $dependentNames;
    }

    /**
     * Resolve all missing dependencies recursively in topological order.
     *
     * @param  array<string>  $activeIds
     * @return array<string>
     */
    private function resolveAllDependenciesRecursive(
        string $pluginId,
        array $activeIds,
        PluginRegistry $registry,
        PluginDependencyResolver $resolver,
    ): array {
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
        Collection $needed,
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

    private function refreshPluginState(): void
    {
        $this->cachedAllPluginRecords = null;
        $this->cachedPluginCounts = null;
        unset($this->cachedTabs);
        $this->flushCachedTableRecords();
        $this->dispatch('plugins-updated');
    }

    private function getCurrentTenant(): ?Tenant
    {
        return function_exists('tenant') ? tenant() : null;
    }
}
