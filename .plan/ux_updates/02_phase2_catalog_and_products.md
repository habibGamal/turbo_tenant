# Phase 2: Catalog & Products UX Modernization

> **Phase Target:** Tabbed Product Architecture, Extra Option Rules, and Table Interactivity  
> **Estimated Effort:** 1.5 hours  
> **Verification Command:** `php artisan test --filter=ProductTest`  

---

## 1. Objectives

1. Refactor [ProductForm.php](file:///e:/turbo_tenant/app/Plugins/CatalogProducts/src/Filament/Resources/Products/Schemas/ProductForm.php) from a single 5-section vertical scroll into **4 structured Tabs**.
2. **User Directive:** Bring selection rules (`min_selections`, `max_selections`, `allow_multiple`) to the **Product form itself**, allowing product-specific overrides when extra options are assigned.
3. Expose the missing database columns (`min_selections`, `max_selections`, `allow_multiple`) in [ExtraOptionForm.php](file:///e:/turbo_tenant/app/Plugins/CatalogProducts/src/Filament/Resources/ExtraOptions/Schemas/ExtraOptionForm.php).
4. Implement drag-and-drop table sorting (`->reorderable('sort_order')`) for Categories and Sections.
5. Upgrade passive status icons into interactive `ToggleColumn`s for instant single-click catalog updates.

---

## 2. Product Form Tabbed Architecture

Refactor [ProductForm.php](file:///e:/turbo_tenant/app/Plugins/CatalogProducts/src/Filament/Resources/Products/Schemas/ProductForm.php) using `Filament\Schemas\Components\Tabs` and `Tabs\Tab`:

```php
Tabs::make('تفاصيل المنتج')
    ->tabs([
        // TAB 1: BASIC INFO & MEDIA
        Tab::make('البيانات الأساسية')
            ->icon('heroicon-o-information-circle')
            ->schema([
                Grid::make(2)->schema([
                    TextInput::make('name')
                        ->label('الاسم (إنجليزي)')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('name_ar')
                        ->label('الاسم بالعربية')
                        ->maxLength(255),
                    Select::make('category_id')
                        ->label('الفئة')
                        ->relationship('category', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),
                    Toggle::make('is_active')
                        ->label('متاح للطلب (نشط)')
                        ->default(true),
                ]),
                Textarea::make('description')
                    ->label('الوصف')
                    ->rows(3)
                    ->columnSpanFull(),
                FileUpload::make('image')
                    ->label('صورة المنتج')
                    ->image()
                    ->imageEditor()
                    ->optimizeForWeb('products')
                    ->helperText('يفضل استخدام صور مربعة بأبعاد لا تقل عن 800x800 بكسل')
                    ->columnSpanFull(),
            ]),

        // TAB 2: PRICING & VARIANTS
        Tab::make('التسعير والتنويعات')
            ->icon('heroicon-o-currency-dollar')
            ->schema([
                Grid::make(2)->schema([
                    TextInput::make('base_price')
                        ->label('السعر الأساسي')
                        ->numeric()
                        ->prefix(fn () => app(\App\Plugins\StoreSettings\src\Services\SettingService::class)->get('currency', 'EGP'))
                        ->required(),
                    TextInput::make('price_after_discount')
                        ->label('السعر بعد الخصم')
                        ->numeric()
                        ->prefix(fn () => app(\App\Plugins\StoreSettings\src\Services\SettingService::class)->get('currency', 'EGP'))
                        ->helperText('اتركه فارغاً إذا لم يكن هناك خصم نشط'),
                ]),
                Toggle::make('sell_by_weight')
                    ->label('بيع المنتج بالوزن')
                    ->default(false)
                    ->live(),
            ]),

        // TAB 3: EXTRAS & WEIGHTS (WITH PRODUCT-LEVEL SELECTION RULES)
        Tab::make('الخيارات الإضافية والأوزان')
            ->icon('heroicon-o-adjustments-horizontal')
            ->schema([
                Section::make('مجموعة الإضافات وقواعد الاختيار')
                    ->description('تحديد الإضافات المسموحة لهذا المنتج وقواعد اختيار العميل')
                    ->schema([
                        Select::make('extra_option_id')
                            ->label('مجموعة الإضافات')
                            ->relationship('extraOption', 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $extra = \App\Plugins\CatalogProducts\src\Models\ExtraOption::find($state);
                                    if ($extra) {
                                        $set('min_selections', $extra->min_selections ?? 0);
                                        $set('max_selections', $extra->max_selections);
                                        $set('allow_multiple', $extra->allow_multiple ?? true);
                                    }
                                }
                            }),
                        Grid::make(3)
                            ->visible(fn (callable $get) => filled($get('extra_option_id')))
                            ->schema([
                                TextInput::make('min_selections')
                                    ->label('الحد الأدنى للاختيارات')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0)
                                    ->helperText('أقل عدد يجب على العميل اختياره (0 يعني اختياري)'),
                                TextInput::make('max_selections')
                                    ->label('الحد الأقصى للاختيارات')
                                    ->numeric()
                                    ->minValue(1)
                                    ->helperText('أقصى عدد خيارات مسموح به (فارغ يعني بلا حد)'),
                                Toggle::make('allow_multiple')
                                    ->label('السماح بتكرار نفس الخيار')
                                    ->default(true),
                            ]),
                    ]),

                Section::make('خيارات الوزن')
                    ->visible(fn (callable $get) => (bool) $get('sell_by_weight'))
                    ->schema([
                        Select::make('weight_options_id')
                            ->label('قالب أوزان المنتج')
                            ->relationship('weightOption', 'name')
                            ->searchable()
                            ->preload()
                            ->required(fn (callable $get) => (bool) $get('sell_by_weight')),
                    ]),
            ]),

        // TAB 4: POS INTEGRATION MAPPINGS
        Tab::make('ربط نقطة البيع (POS)')
            ->icon('heroicon-o-computer-desktop')
            ->schema([
                Repeater::make('posMappings')
                    ->relationship()
                    ->schema([
                        Select::make('branch_id')
                            ->label('الفرع')
                            ->relationship('branch', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('variant_id')
                            ->relationship('variant', 'name')
                            ->searchable()
                            ->preload()
                            ->label('التنويع (اختياري)'),
                        Select::make('extra_option_item_id')
                            ->relationship('extraOptionItem', 'name')
                            ->searchable()
                            ->preload()
                            ->label('عنصر الإضافة (اختياري)'),
                        PosItemSelect::make('pos_item_id')
                            ->required(),
                    ])
                    ->columns(4)
                    ->collapsible()
                    ->defaultItems(0)
                    ->addActionLabel('إضافة ربط جديد مع فرع POS'),
            ]),
    ])
    ->columnSpanFull()
```

---

## 3. Extra Options Form: Exposing Missing Selection Rules

Update [ExtraOptionForm.php](file:///e:/turbo_tenant/app/Plugins/CatalogProducts/src/Filament/Resources/ExtraOptions/Schemas/ExtraOptionForm.php):
Add the missing selection rules section right under basic info:

```php
Section::make('قواعد الاختيار الافتراضية')
    ->description('القواعد الافتراضية التي تنطبق على هذه المجموعة عند ربطها بالمنتجات')
    ->schema([
        Grid::make(3)->schema([
            TextInput::make('min_selections')
                ->label('الحد الأدنى للاختيارات الافتراضي')
                ->numeric()
                ->default(0)
                ->minValue(0)
                ->helperText('0 = اختياري بالكامل'),
            TextInput::make('max_selections')
                ->label('الحد الأقصى للاختيارات الافتراضي')
                ->numeric()
                ->minValue(1)
                ->helperText('اتركه فارغاً للاختيار غير المحدود'),
            Toggle::make('allow_multiple')
                ->label('السماح باختيار نفس العنصر أكثر من مرة')
                ->default(true),
        ]),
    ])
```

---

## 4. Tables Interactivity & Reordering Improvements

### A. Categories Table ([CategoriesTable.php](file:///e:/turbo_tenant/app/Plugins/CatalogProducts/src/Filament/Resources/Categories/Tables/CategoriesTable.php))
- Add: `->reorderable('sort_order')` for row dragging.
- Add: `TextColumn::make('products_count')->counts('products')->label('عدد المنتجات')->badge()->color('info')`.
- Replace `IconColumn::make('is_active')` with `ToggleColumn::make('is_active')->label('نشط')`.

### B. Products Table ([ProductsTable.php](file:///e:/turbo_tenant/app/Plugins/CatalogProducts/src/Filament/Resources/Products/Tables/ProductsTable.php))
- Replace `IconColumn::make('is_active')` with `ToggleColumn::make('is_active')->label('نشط')`.
- Add variant badge: `TextColumn::make('variants_count')->counts('variants')->label('التنويعات')->badge()->color('gray')`.
- Replace hardcoded `->money('USD')` with dynamic tenant store currency.
- Add discount pill: When `price_after_discount` exists, show the calculated discount percentage badge.

### C. Sections Table ([SectionsTable.php](file:///e:/turbo_tenant/app/Plugins/CatalogProducts/src/Filament/Resources/Sections/Tables/SectionsTable.php))
- Add: `->reorderable('sort_order')`.
- Replace `IconColumn::make('is_active')` with `ToggleColumn::make('is_active')`.
- Format `location` badge:
  ```php
  TextColumn::make('location')
      ->label('الموقع')
      ->badge()
      ->formatStateUsing(fn (string $state): string => match ($state) {
          'home' => 'الرئيسية',
          'menu' => 'القائمة',
          'featured' => 'مميز',
          default => $state,
      })
      ->color(fn (string $state): string => match ($state) {
          'home' => 'primary',
          'menu' => 'info',
          'featured' => 'warning',
          default => 'gray',
      })
  ```

---

## 5. Verification & Acceptance Criteria

- [ ] Product edit page renders as 4 tabs without vertical clutter.
- [ ] Setting `min_selections` and `max_selections` on both ExtraOption and Product forms correctly persists.
- [ ] Categories and sections can be dragged and dropped to reorder on the list page.
- [ ] Products and categories can be enabled/disabled directly from table toggle switches.
