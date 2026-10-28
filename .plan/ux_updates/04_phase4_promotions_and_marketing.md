# Phase 4: Promotions, Packages & Marketing UX

> **Phase Target:** Coupon & Package Form Tabs, Gradient Presets, Slider Localization & Push Notification Optimization  
> **Estimated Effort:** 1.5 hours  
> **Verification Command:** `php artisan test --filter=CouponTest`  

---

## 1. Objectives

1. Organize the 8-section [CouponForm.php](file:///e:/turbo_tenant/app/Plugins/CouponsPromotions/src/Filament/Resources/Coupons/Schemas/CouponForm.php) into **3 clean Tabs**.
2. Fix the English badge output (`Percentage` / `Fixed`) in [CouponsTable.php](file:///e:/turbo_tenant/app/Plugins/CouponsPromotions/src/Filament/Resources/Coupons/Tables/CouponsTable.php) to display proper Arabic labels.
3. Organize [PackageForm.php](file:///e:/turbo_tenant/app/Plugins/ComboPackages/src/Filament/Resources/Packages/Schemas/PackageForm.php) into **3 Tabs** and replace raw Tailwind CSS string inputs with intuitive gradient and icon preset pickers.
4. Translate [HeroSliderResource.php](file:///e:/turbo_tenant/app/Plugins/HeroBanners/src/Filament/Resources/HeroSliderResource.php) form fields from English into Arabic and add row reordering.
5. Fix the critical memory bottleneck in [BulkNotificationForm.php](file:///e:/turbo_tenant/app/Plugins/PushNotifications/src/Filament/Resources/BulkNotifications/Schemas/BulkNotificationForm.php) by replacing heavy `CheckboxList::options(User::pluck())` with an asynchronous searchable `Select`.

---

## 2. Coupons UX Overhaul

### A. Tabbed Form Layout
Refactor [CouponForm.php](file:///e:/turbo_tenant/app/Plugins/CouponsPromotions/src/Filament/Resources/Coupons/Schemas/CouponForm.php) into 3 Tabs:

```php
Tabs::make('بيانات الكوبون')
    ->tabs([
        Tab::make('الخصم والرمز')
            ->icon('heroicon-o-ticket')
            ->schema([
                Grid::make(2)->schema([
                    TextInput::make('code')
                        ->label('رمز الكوبون (البروموكود)')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),
                    TextInput::make('name')
                        ->label('اسم الحملة الترويجية')
                        ->maxLength(255),
                    Select::make('type')
                        ->label('نوع الخصم')
                        ->required()
                        ->options([
                            'percentage' => 'نسبة مئوية (%)',
                            'fixed' => 'مبلغ ثابت',
                        ])
                        ->default('percentage')
                        ->live(),
                    TextInput::make('value')
                        ->label('قيمة الخصم')
                        ->required()
                        ->numeric()
                        ->minValue(0)
                        ->suffix(fn (callable $get) => $get('type') === 'percentage' ? '%' : 'EGP'),
                    DateTimePicker::make('expiry_date')
                        ->label('تاريخ ووقت الانتهاء')
                        ->required()
                        ->minDate(now()),
                    TextInput::make('max_usage')
                        ->label('الحد الأقصى لمرات الاستخدام')
                        ->numeric()
                        ->helperText('اتركه فارغاً للاستخدام غير المحدود'),
                    Toggle::make('is_active')
                        ->label('كوبون نشط')
                        ->default(true),
                ]),
                Textarea::make('description')
                    ->label('وصف الكوبون وشروطه للعملاء')
                    ->rows(2)
                    ->columnSpanFull(),
            ]),

        Tab::make('شروط الطلب والمنتجات')
            ->icon('heroicon-o-shopping-bag')
            ->schema([
                Grid::make(2)->schema([
                    TextInput::make('conditions.min_order_total')
                        ->label('الحد الأدنى لقيمة الطلب')
                        ->numeric()
                        ->helperText('أقل مبلغ سلة مطلوب لتفعيل الكوبون'),
                    TextInput::make('conditions.max_order_total')
                        ->label('الحد الأقصى لقيمة الطلب')
                        ->numeric(),
                ]),
                Select::make('conditions.applicable_to.type')
                    ->label('نطاق تطبيق الكوبون')
                    ->options([
                        'all' => 'جميع منتجات المتجر',
                        'products' => 'منتجات محددة فقط',
                        'categories' => 'فئات معينة فقط',
                    ])
                    ->default('all')
                    ->live(),
                Select::make('conditions.applicable_to.product_ids')
                    ->label('اختر المنتجات المشمولة')
                    ->multiple()
                    ->searchable()
                    ->relationship('products', 'name')
                    ->visible(fn (callable $get) => $get('conditions.applicable_to.type') === 'products'),
                Select::make('conditions.applicable_to.category_ids')
                    ->label('اختر الفئات المشمولة')
                    ->multiple()
                    ->searchable()
                    ->relationship('categories', 'name')
                    ->visible(fn (callable $get) => $get('conditions.applicable_to.type') === 'categories'),
            ]),

        Tab::make('القيود والشحن والتوقيت')
            ->icon('heroicon-o-clock')
            ->schema([
                Section::make('قيود الشحن')->schema([
                    Toggle::make('conditions.shipping.free_shipping')
                        ->label('يمنح شحن مجاني')
                        ->live(),
                    TextInput::make('conditions.shipping.free_shipping_threshold')
                        ->label('الحد الأدنى للشحن المجاني')
                        ->numeric()
                        ->visible(fn (callable $get) => (bool) $get('conditions.shipping.free_shipping')),
                ]),
                Section::make('قيود الاستخدام للعملاء')->schema([
                    Toggle::make('conditions.usage_restrictions.first_order_only')
                        ->label('صالح للطلب الأول للعميل فقط'),
                ]),
            ]),
    ])
    ->columnSpanFull()
```

### B. Table Localization Fix
In [CouponsTable.php](file:///e:/turbo_tenant/app/Plugins/CouponsPromotions/src/Filament/Resources/Coupons/Tables/CouponsTable.php#L43):
```php
TextColumn::make('type')
    ->label('النوع')
    ->badge()
    ->formatStateUsing(fn (string $state): string => match ($state) {
        'percentage' => 'نسبة مئوية',
        'fixed' => 'مبلغ ثابت',
        default => $state,
    })
    ->color(fn (string $state): string => match ($state) {
        'percentage' => 'success',
        'fixed' => 'info',
        default => 'gray',
    })
```

---

## 3. Combo Packages: Replacing Raw CSS Inputs with Presets

In [PackageForm.php](file:///e:/turbo_tenant/app/Plugins/ComboPackages/src/Filament/Resources/Packages/Schemas/PackageForm.php#L105-L115):

```php
// Replace raw CSS string with visual gradient presets
Select::make('gradient')
    ->label('التدرج اللوني لبطاقة الباقة')
    ->options([
        'from-orange-500/20 via-red-500/10 to-pink-500/20' => 'برتقالي وأحمر دافئ (Sunset)',
        'from-emerald-500/20 via-teal-500/10 to-cyan-500/20' => 'أخضر وزمردي منعش (Fresh)',
        'from-blue-500/20 via-indigo-500/10 to-purple-500/20' => 'أزرق وبنفسجي ملكي (Royal)',
        'from-amber-500/20 via-yellow-500/10 to-orange-500/20' => 'ذهبي ومميز (Gold)',
        'from-zinc-500/20 via-neutral-500/10 to-stone-500/20' => 'رمادي كلاسيكي (Neutral)',
    ])
    ->default('from-orange-500/20 via-red-500/10 to-pink-500/20')
    ->required(),

// Replace raw icon input with preset icon selector
Select::make('icon')
    ->label('أيقونة الباقة')
    ->options([
        'gift' => 'هدية (Gift)',
        'fire' => 'لهب / مميز (Fire)',
        'sparkles' => 'بريق / حصري (Sparkles)',
        'bolt' => 'برق / وجبة سريعة (Bolt)',
        'heart' => 'مفضل (Heart)',
        'tag' => 'توفير / خصم (Tag)',
    ])
    ->default('gift')
    ->required()
```

---

## 4. Push Notifications: Memory Optimization

In [BulkNotificationForm.php](file:///e:/turbo_tenant/app/Plugins/PushNotifications/src/Filament/Resources/BulkNotifications/Schemas/BulkNotificationForm.php#L87-L98):

Replace static `CheckboxList` that pulls all database users into memory with an asynchronous searchable multiselect:

```php
Select::make('target_user_ids')
    ->label('اختر المستخدمين المستهدفين')
    ->multiple()
    ->searchable()
    ->visible(fn (callable $get) => ! $get('send_to_all'))
    ->required(fn (callable $get) => ! $get('send_to_all'))
    ->getSearchResultsUsing(fn (string $search) => 
        \App\Models\User::whereNotNull('expo_token')
            ->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            })
            ->limit(30)
            ->pluck('name', 'id')
    )
    ->getOptionLabelsUsing(fn (array $values) => 
        \App\Models\User::whereIn('id', $values)->pluck('name', 'id')
    )
```

---

## 5. Verification & Acceptance Criteria

- [ ] Coupon form renders cleanly across 3 tabs without overwhelming users.
- [ ] Coupon type in table renders as "نسبة مئوية" / "مبلغ ثابت".
- [ ] Combo package gradient and icon fields offer clean dropdown presets instead of raw CSS input.
- [ ] Push notification user selection loads instantaneously via AJAX without preloading thousands of database rows.
- [ ] Slider is titled "سلايدر" with full Arabic form labels.
