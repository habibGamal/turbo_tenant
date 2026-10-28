<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Statistics Row --}}
        @php
            $stats = $this->stats;
        @endphp
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <x-filament::section compact>
                <div class="flex items-center justify-between gap-3">
                    <div class="space-y-1">
                        <span class="text-xs sm:text-sm font-medium text-gray-500 dark:text-gray-400">إجمالي الإضافات</span>
                        <div class="text-xl sm:text-2xl font-bold tracking-tight text-gray-950 dark:text-white">
                            {{ $stats['total'] }}
                        </div>
                    </div>
                    <div class="p-2.5 rounded-xl bg-primary-50 dark:bg-primary-950/60 text-primary-600 dark:text-primary-400">
                        <x-filament::icon icon="heroicon-o-puzzle-piece" class="w-6 h-6" />
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section compact>
                <div class="flex items-center justify-between gap-3">
                    <div class="space-y-1">
                        <span class="text-xs sm:text-sm font-medium text-gray-500 dark:text-gray-400">الإضافات المفعلة</span>
                        <div class="text-xl sm:text-2xl font-bold tracking-tight text-success-600 dark:text-success-400">
                            {{ $stats['active'] }}
                        </div>
                    </div>
                    <div class="p-2.5 rounded-xl bg-success-50 dark:bg-success-950/60 text-success-600 dark:text-success-400">
                        <x-filament::icon icon="heroicon-o-check-circle" class="w-6 h-6" />
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section compact>
                <div class="flex items-center justify-between gap-3">
                    <div class="space-y-1">
                        <span class="text-xs sm:text-sm font-medium text-gray-500 dark:text-gray-400">متاحة للتثبيت</span>
                        <div class="text-xl sm:text-2xl font-bold tracking-tight text-info-600 dark:text-info-400">
                            {{ $stats['available'] }}
                        </div>
                    </div>
                    <div class="p-2.5 rounded-xl bg-info-50 dark:bg-info-950/60 text-info-600 dark:text-info-400">
                        <x-filament::icon icon="heroicon-o-arrow-down-tray" class="w-6 h-6" />
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section compact>
                <div class="flex items-center justify-between gap-3">
                    <div class="space-y-1">
                        <span class="text-xs sm:text-sm font-medium text-gray-500 dark:text-gray-400">إضافات أساسية</span>
                        <div class="text-xl sm:text-2xl font-bold tracking-tight text-warning-600 dark:text-warning-400">
                            {{ $stats['core'] }}
                        </div>
                    </div>
                    <div class="p-2.5 rounded-xl bg-warning-50 dark:bg-warning-950/60 text-warning-600 dark:text-warning-400">
                        <x-filament::icon icon="heroicon-o-lock-closed" class="w-6 h-6" />
                    </div>
                </div>
            </x-filament::section>
        </div>

        {{-- Filters & Search Section --}}
        <x-filament::section compact>
            <div class="flex flex-col gap-4">
                {{-- Status Filter Tabs --}}
                <x-filament::tabs label="فلاتر حالة الإضافات">
                    <x-filament::tabs.item
                        :active="$selectedStatus === 'all'"
                        wire:click="$set('selectedStatus', 'all')"
                        :badge="$stats['total']"
                        badge-color="gray"
                        icon="heroicon-m-squares-2x2"
                    >
                        الكل
                    </x-filament::tabs.item>

                    <x-filament::tabs.item
                        :active="$selectedStatus === 'installed'"
                        wire:click="$set('selectedStatus', 'installed')"
                        :badge="$stats['active']"
                        badge-color="success"
                        icon="heroicon-m-check-circle"
                    >
                        المفعلة
                    </x-filament::tabs.item>

                    <x-filament::tabs.item
                        :active="$selectedStatus === 'not_installed'"
                        wire:click="$set('selectedStatus', 'not_installed')"
                        :badge="$stats['available']"
                        badge-color="info"
                        icon="heroicon-m-arrow-down-tray"
                    >
                        متاحة للتثبيت
                    </x-filament::tabs.item>

                    <x-filament::tabs.item
                        :active="$selectedStatus === 'core'"
                        wire:click="$set('selectedStatus', 'core')"
                        :badge="$stats['core']"
                        badge-color="warning"
                        icon="heroicon-m-lock-closed"
                    >
                        الأساسية (Core)
                    </x-filament::tabs.item>
                </x-filament::tabs>

                {{-- Search and Category Filters --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div class="md:col-span-2">
                        <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                            <x-filament::input
                                type="search"
                                wire:model.live.debounce.300ms="search"
                                placeholder="ابحث باسم الإضافة أو الوصف أو المعرف..."
                            />
                        </x-filament::input.wrapper>
                    </div>

                    <div>
                        <x-filament::input.wrapper prefix-icon="heroicon-m-funnel">
                            <x-filament::input.select wire:model.live="selectedCategory">
                                @foreach ($this->getCategories() as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </div>
                </div>
            </div>
        </x-filament::section>

        {{-- Plugins Grid --}}
        @php
            $plugins = $this->plugins;
        @endphp

        @if ($plugins->isNotEmpty())
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                @foreach ($plugins as $plugin)
                    @php
                        $status = $plugin['status'];
                        $isCore = $plugin['is_core'];
                        $isActive = $plugin['is_active'];
                        $isInstalled = $plugin['is_installed'];

                        $statusColor = match (true) {
                            $isCore => 'warning',
                            $isActive => 'success',
                            $status === 'deactivated' => 'warning',
                            default => 'gray',
                        };

                        $statusLabel = match (true) {
                            $isCore => 'أساسي',
                            $isActive => 'مفعل',
                            $status === 'deactivated' => 'معطل',
                            default => 'غير مثبت',
                        };

                        $categoryIcon = match ($plugin['category']) {
                            'foundation' => 'heroicon-o-cube',
                            'commerce' => 'heroicon-o-shopping-bag',
                            'marketing' => 'heroicon-o-megaphone',
                            'communication' => 'heroicon-o-chat-bubble-left-right',
                            'storefront' => 'heroicon-o-building-storefront',
                            default => 'heroicon-o-puzzle-piece',
                        };
                    @endphp

                    <x-filament::section
                        :icon="$categoryIcon"
                        compact
                        class="flex flex-col justify-between h-full"
                    >
                        {{-- Heading --}}
                        <x-slot name="heading">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-base text-gray-950 dark:text-white truncate">
                                    {{ $plugin['name'] }}
                                </span>
                            </div>
                        </x-slot>

                        {{-- After Header --}}
                        <x-slot name="afterHeader">
                            <x-filament::badge :color="$statusColor" size="sm">
                                {{ $statusLabel }}
                            </x-filament::badge>
                        </x-slot>

                        {{-- Body Content --}}
                        <div class="space-y-3">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <x-filament::badge color="gray" size="xs">
                                    {{ $plugin['category_label'] }}
                                </x-filament::badge>
                                <x-filament::badge color="gray" size="xs">
                                    v{{ $plugin['version'] }}
                                </x-filament::badge>
                                @if ($isCore)
                                    <x-filament::badge color="warning" size="xs" icon="heroicon-m-lock-closed">
                                        أساسي
                                    </x-filament::badge>
                                @endif
                                @if ($plugin['author'])
                                    <span class="text-xs text-gray-500 dark:text-gray-400 ms-auto">
                                        {{ $plugin['author'] }}
                                    </span>
                                @endif
                            </div>

                            <p class="text-sm text-gray-600 dark:text-gray-400 line-clamp-2 min-h-[2.5rem]">
                                {{ $plugin['description'] ?? 'لا يوجد وصف متوفر لهذه الإضافة.' }}
                            </p>

                            {{-- Dependencies --}}
                            @if (!empty($plugin['dependencies']))
                                <div class="pt-2 border-t border-gray-100 dark:border-gray-800">
                                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400 block mb-1">
                                        المتطلبات:
                                    </span>
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($plugin['dependencies_detail'] as $dep)
                                            <x-filament::badge
                                                :color="$dep['is_active'] ? 'success' : 'danger'"
                                                :icon="$dep['is_active'] ? 'heroicon-m-check' : 'heroicon-m-x-mark'"
                                                size="xs"
                                            >
                                                {{ $dep['name'] }}
                                            </x-filament::badge>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- Footer --}}
                        <x-slot name="footer">
                            <div class="flex items-center justify-between gap-2 pt-2 border-t border-gray-100 dark:border-gray-800">
                                <span class="text-xs text-gray-400 font-mono truncate max-w-[120px]">
                                    {{ $plugin['id'] }}
                                </span>

                                <div class="flex items-center gap-2">
                                    @if ($isActive)
                                        @if ($isCore)
                                            <x-filament::button
                                                disabled
                                                color="gray"
                                                icon="heroicon-m-lock-closed"
                                                size="sm"
                                            >
                                                مثبت (أساسي)
                                            </x-filament::button>
                                        @else
                                            <x-filament::button
                                                color="warning"
                                                icon="heroicon-m-pause"
                                                size="sm"
                                                outlined
                                                wire:click="deactivatePlugin('{{ $plugin['id'] }}')"
                                            >
                                                تعطيل
                                            </x-filament::button>

                                            <x-filament::button
                                                color="danger"
                                                icon="heroicon-m-trash"
                                                size="sm"
                                                outlined
                                                wire:click="promptUninstall('{{ $plugin['id'] }}')"
                                            >
                                                إلغاء التثبيت
                                            </x-filament::button>
                                        @endif
                                    @elseif ($status === 'deactivated')
                                        <x-filament::button
                                            color="success"
                                            icon="heroicon-m-play"
                                            size="sm"
                                            wire:click="installPlugin('{{ $plugin['id'] }}')"
                                        >
                                            تفعيل
                                        </x-filament::button>

                                        <x-filament::button
                                            color="danger"
                                            icon="heroicon-m-trash"
                                            size="sm"
                                            outlined
                                            wire:click="promptUninstall('{{ $plugin['id'] }}')"
                                        >
                                            إلغاء التثبيت
                                        </x-filament::button>
                                    @else
                                        <x-filament::button
                                            color="primary"
                                            icon="heroicon-m-arrow-down-tray"
                                            size="sm"
                                            wire:click="installPlugin('{{ $plugin['id'] }}')"
                                        >
                                            تثبيت وتفعيل
                                        </x-filament::button>
                                    @endif
                                </div>
                            </div>
                        </x-slot>
                    </x-filament::section>
                @endforeach
            </div>
        @else
            <x-filament::empty-state
                icon="heroicon-o-puzzle-piece"
                heading="لا توجد إضافات تطابق معايير البحث"
                description="حاول تعديل كلمة البحث أو تغيير الفلتر المختار."
            >
                <x-slot name="footer">
                    <x-filament::button
                        color="gray"
                        size="sm"
                        wire:click="resetFilters"
                    >
                        إعادة ضبط الفلاتر
                    </x-filament::button>
                </x-slot>
            </x-filament::empty-state>
        @endif

        {{-- Dependency Resolution Modal --}}
        <x-filament::modal
            id="dependency-modal"
            width="md"
            icon="heroicon-o-puzzle-piece"
            icon-color="warning"
            heading="متطلبات التثبيت الإضافية"
            description="تتطلب هذه الإضافة تفعيل الإضافات التابعة لها لضمان عملها بشكل سليم."
        >
            @if ($targetPluginId && count($pendingMissingDependencies))
                <div class="space-y-3">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        سيقوم النظام بتثبيت وتفعيل هذه الإضافات تلقائياً بالترتيب المطلوب:
                    </p>
                    <div class="space-y-2">
                        @foreach ($pendingMissingDependencies as $dep)
                            <div class="flex items-center justify-between p-2.5 rounded-lg bg-gray-50 dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700">
                                <div class="flex items-center gap-2">
                                    <x-filament::icon icon="heroicon-m-plus-circle" class="w-5 h-5 text-warning-500" />
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $dep['name'] }}</span>
                                </div>
                                <x-filament::badge color="gray" size="xs">
                                    {{ $dep['id'] }}
                                </x-filament::badge>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <x-slot name="footer">
                <div class="flex items-center justify-end gap-3 w-full">
                    <x-filament::button
                        color="gray"
                        outlined
                        x-on:click="$dispatch('close-modal', { id: 'dependency-modal' })"
                    >
                        إلغاء
                    </x-filament::button>

                    <x-filament::button
                        color="primary"
                        icon="heroicon-m-check"
                        wire:click="confirmInstallWithDependencies"
                    >
                        تثبيت وتفعيل الكل
                    </x-filament::button>
                </div>
            </x-slot>
        </x-filament::modal>

        {{-- Uninstall Confirmation Modal --}}
        <x-filament::modal
            id="uninstall-modal"
            width="md"
            icon="heroicon-o-trash"
            icon-color="danger"
            heading="تأكيد إلغاء التثبيت وحذف البيانات"
            description="هل أنت متأكد من رغبتك في إلغاء تثبيت هذه الإضافة؟"
        >
            @if ($uninstallPluginId)
                <div class="space-y-3">
                    <div class="p-3.5 rounded-xl bg-danger-50 dark:bg-danger-950/40 border border-danger-200 dark:border-danger-800 space-y-1.5">
                        <p class="text-sm font-bold text-danger-900 dark:text-danger-200">
                            {{ $uninstallPluginName }}
                        </p>
                        <p class="text-xs text-danger-700 dark:text-danger-300">
                            تحذير: سيتم التراجع عن جميع جداول قاعدة البيانات وحذف مجلدات التخزين الخاصة بهذه الإضافة لمتجرك نهائياً. لا يمكن التراجع عن هذا الإجراء!
                        </p>
                    </div>
                </div>
            @endif

            <x-slot name="footer">
                <div class="flex items-center justify-end gap-3 w-full">
                    <x-filament::button
                        color="gray"
                        outlined
                        x-on:click="$dispatch('close-modal', { id: 'uninstall-modal' })"
                    >
                        إلغاء
                    </x-filament::button>

                    <x-filament::button
                        color="danger"
                        icon="heroicon-m-trash"
                        wire:click="confirmUninstall"
                    >
                        نعم، إلغاء التثبيت وحذف البيانات
                    </x-filament::button>
                </div>
            </x-slot>
        </x-filament::modal>

    </div>
</x-filament-panels::page>
