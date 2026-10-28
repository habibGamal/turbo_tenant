# Master Table Intelligence Matrix: Filters, Sorting & Hidden Columns

> **Scope:** Exhaustive specification for all tables across the 18 Filament resources and pages  
> **Destination Path:** `.plan/ux_updates/07_table_filters_sorting_and_columns_matrix.md`  
> **Purpose:** Eliminate operational blindspots, establish consistent default sorting, enable drag & drop reordering, and provide rich filtering for high-velocity store management.

---

## 1. System-Wide Table Audit Matrix

| Resource / Table | Missing Crucial Filters | Sorting Standard & Reordering | Important Columns to Add / Un-hide |
| :--- | :--- | :--- | :--- |
| **Orders**<br>([OrdersTable.php](file:///e:/turbo_tenant/app/Plugins/OrderManagement/src/Filament/Resources/Orders/Tables/OrdersTable.php)) | • Date Range (`created_at` from - to)<br>• Payment Status (`paid`, `unpaid`, `failed`, `refunded`)<br>• Payment Method (`card`, `wallet`, `cod`, `kiosk`)<br>• POS Sync Status (`failed`, `sent`, `pending`) | • `defaultSort('created_at', 'desc')`<br>• Sortable on total, customer, status, branch | • **Customer Phone:** Subtext under name + copyable<br>• **Payment Status Badge:** Color-coded enum<br>• **POS Status Badge:** Crucial for sync alerts<br>• **Items Count Badge:** Total order items<br>• **Delivery Area Name:** Area & address snapshot |
| **Products**<br>([ProductsTable.php](file:///e:/turbo_tenant/app/Plugins/CatalogProducts/src/Filament/Resources/Products/Tables/ProductsTable.php)) | • "On Sale" (Has active discount)<br>• Missing Image filter (data quality audit)<br>• Extra Option assigned filter<br>• Weight Selling filter | • Add missing `defaultSort('created_at', 'desc')`<br>• Sortable on name_ar | • **`ToggleColumn` for `is_active`** (instant toggle)<br>• **Variants Count Badge:** `variants_count`<br>• **Discount % Badge:** (e.g. `-25%`)<br>• **POS Mapped Indicator:** Icon indicating POS readiness |
| **Variants**<br>([VariantsRelationManager.php](file:///e:/turbo_tenant/app/Plugins/CatalogProducts/src/Filament/Resources/Products/RelationManagers/VariantsRelationManager.php)) | • Availability Filter (`is_available`) | • `defaultSort('sort_order', 'asc')`<br>• **Add `->reorderable('sort_order')`** | • **`ToggleColumn` for `is_available`**<br>• Price diff relative to base price |
| **Categories**<br>([CategoriesTable.php](file:///e:/turbo_tenant/app/Plugins/CatalogProducts/src/Filament/Resources/Categories/Tables/CategoriesTable.php)) | • Has Products vs Empty categories | • `defaultSort('sort_order', 'asc')`<br>• **Add `->reorderable('sort_order')`** | • **Products Count Badge:** `products_count`<br>• **`ToggleColumn` for `is_active`** |
| **Extra Options**<br>([ExtraOptionsTable.php](file:///e:/turbo_tenant/app/Plugins/CatalogProducts/src/Filament/Resources/ExtraOptions/Tables/ExtraOptionsTable.php)) | • Mandatory vs Optional (`min_selections > 0`)<br>• Single vs Multi-choice (`allow_multiple`) | • Add missing `defaultSort('created_at', 'desc')` | • **`min_selections` (الحد الأدنى):** Missing!<br>• **`max_selections` (الحد الأقصى):** Missing!<br>• **`allow_multiple`:** Missing!<br>• **`products_count`:** Linked products count |
| **Weight Options**<br>([WeightOptionsTable.php](file:///e:/turbo_tenant/app/Plugins/CatalogProducts/src/Filament/Resources/WeightOptions/Tables/WeightOptionsTable.php)) | • Filter by Unit (`kg`, `g`, `lb`) | • Add missing `defaultSort('name', 'asc')` | • **Values Chips Preview:** List actual values: `[0.25 kg] [0.5 kg] [1 kg]`<br>• Linked products count |
| **Sections**<br>([SectionsTable.php](file:///e:/turbo_tenant/app/Plugins/CatalogProducts/src/Filament/Resources/Sections/Tables/SectionsTable.php)) | • Filter by Location (`home`, `menu`, `featured`) | • `defaultSort('sort_order', 'asc')`<br>• **Add `->reorderable('sort_order')`** | • **Localized Location Badge:** (الرئيسية, القائمة, مميز)<br>• **`ToggleColumn` for `is_active`** |
| **Coupons**<br>([CouponsTable.php](file:///e:/turbo_tenant/app/Plugins/CouponsPromotions/src/Filament/Resources/Coupons/Tables/CouponsTable.php)) | • Exhausted filter (`usage_count >= max_usage`)<br>• Free shipping coupons filter | • Add missing `defaultSort('created_at', 'desc')` | • **Unified Status Badge:** (`نشط`, `منتهي`, `استنفذ`, `معطل`)<br>• **Free Shipping Badge**<br>• **Min Order Threshold**<br>• **`ToggleColumn` for `is_active`** |
| **Packages**<br>([PackagesTable.php](file:///e:/turbo_tenant/app/Plugins/ComboPackages/src/Filament/Resources/Packages/Tables/PackagesTable.php)) | • Filter by Featured<br>• Filter by Validity (Active now / Expired) | • `defaultSort('sort_order', 'asc')`<br>• **Add `->reorderable('sort_order')`** | • **`name_ar`:** Omitted from table!<br>• **Live Validity Status Indicator**<br>• **`ToggleColumn` for `is_active` and `is_featured`** |
| **Branches**<br>([BranchesTable.php](file:///e:/turbo_tenant/app/Plugins/LocationsDelivery/src/Filament/Resources/Branches/Tables/BranchesTable.php)) | • Active status filter | • `defaultSort('name', 'asc')` | • **Covered Areas Count Badge**<br>• Total Orders count<br>• **`ToggleColumn` for `is_active`**<br>• Clickable URL button |
| **Governorates**<br>([GovernoratesTable.php](file:///e:/turbo_tenant/app/Plugins/LocationsDelivery/src/Filament/Resources/Governorates/Tables/GovernoratesTable.php)) | • Active status filter | • `defaultSort('sort_order', 'asc')`<br>• **Add `->reorderable('sort_order')`** | • **`ToggleColumn` for `is_active`** |
| **Areas**<br>([AreasTable.php](file:///e:/turbo_tenant/app/Plugins/LocationsDelivery/src/Filament/Resources/Areas/Tables/AreasTable.php)) | • Free delivery areas (`shipping_cost = 0`) | • `defaultSort('sort_order', 'asc')`<br>• **Add `->reorderable('sort_order')`** | • **Inline Editable `shipping_cost`** (`TextInputColumn`)<br>• **`ToggleColumn` for `is_active`** |
| **Users**<br>([UsersTable.php](file:///e:/turbo_tenant/app/Filament/Resources/Users/Tables/UsersTable.php)) | • Has Orders filter (Buyers vs Registered)<br>• Verified email filter<br>• Registration date range | • Add missing `defaultSort('created_at', 'desc')` | • **Customer Phone Number:** 100% missing!<br>• **Orders Count Badge**<br>• Total Lifetime Spend (LTV)<br>• Last Order Date |
| **Guest Users**<br>([GuestUsersTable.php](file:///e:/turbo_tenant/app/Plugins/GuestCheckout/src/Filament/Resources/GuestUsers/Tables/GuestUsersTable.php)) | • Governorate filter<br>• Date range filter | • `defaultSort('created_at', 'desc')` | • Total Orders Spend<br>• Last Order Date |
| **Reviews**<br>([ReviewResource.php](file:///e:/turbo_tenant/app/Plugins/ProductReviews/src/Filament/Resources/ReviewResource.php)) | • **Pending Approval** (`is_approved = false`)<br>• Rating filter (1 to 5 stars)<br>• Product filter | • Add missing `defaultSort('created_at', 'desc')` | • **`is_approved` Status Column:** Missing!<br>• **Visual Star Rating:** `⭐⭐⭐⭐⭐`<br>• Verified Buyer badge |
| **Push Notifications**<br>([BulkNotificationsTable.php](file:///e:/turbo_tenant/app/Plugins/PushNotifications/src/Filament/Resources/BulkNotifications/Tables/BulkNotificationsTable.php)) | • Scheduled vs Immediate filter<br>• Date range filter | • `defaultSort('created_at', 'desc')` | • **Success Rate % Badge** (e.g. `98%`)<br>• Body snippet preview |
| **Sliders**<br>([HeroSliderResource.php](file:///e:/turbo_tenant/app/Plugins/HeroBanners/src/Filament/Resources/HeroSliderResource.php)) | • Active status filter | • `defaultSort('sort_order', 'asc')`<br>• **Add `->reorderable('sort_order')`** | • **`title_ar`:** Missing Arabic title!<br>• Target CTA link badge<br>• **`ToggleColumn` for `is_active`** |
| **CMS Pages**<br>([PagesTable.php](file:///e:/turbo_tenant/app/Plugins/CmsPages/src/Filament/Resources/Pages/Tables/PagesTable.php)) | • Active status filter | • Add missing `defaultSort('created_at', 'desc')` | • **`title_ar`:** Missing<br>• **View Live Page Action** (`/page/{slug}`)<br>• **`ToggleColumn` for `is_active`** |

---

## 2. Blueprint Implementation Code Snippets

### A. Orders Table ([OrdersTable.php](file:///e:/turbo_tenant/app/Plugins/OrderManagement/src/Filament/Resources/Orders/Tables/OrdersTable.php))

```php
public static function configure(Table $table): Table
{
    return $table
        ->defaultSort('created_at', 'desc')
        ->columns([
            TextColumn::make('order_number')
                ->label('رقم الطلب')
                ->searchable()
                ->sortable()
                ->copyable()
                ->weight('bold'),

            TextColumn::make('customer')
                ->label('العميل')
                ->state(fn ($record) => $record->getCustomerName())
                ->description(fn ($record) => $record->getCustomerPhone() ?? 'بلا هاتف')
                ->searchable(query: function ($query, $search) {
                    return $query->where(function ($q) use ($search) {
                        $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"))
                          ->orWhereHas('guestUser', fn ($g) => $g->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"));
                    });
                })
                ->copyable(),

            TextColumn::make('type')
                ->label('النوع')
                ->badge(),

            TextColumn::make('status')
                ->label('الحالة')
                ->badge(),

            TextColumn::make('payment_status')
                ->label('حالة الدفع')
                ->badge(),

            TextColumn::make('pos_status')
                ->label('مزامنة POS')
                ->badge(),

            TextColumn::make('items_count')
                ->counts('items')
                ->label('العناصر')
                ->badge()
                ->color('gray')
                ->toggleable(),

            TextColumn::make('branch.name')
                ->label('الفرع')
                ->searchable()
                ->sortable(),

            TextColumn::make('total')
                ->label('الإجمالي')
                ->money(fn () => app(\App\Plugins\StoreSettings\src\Services\SettingService::class)->get('currency', 'EGP'))
                ->sortable()
                ->weight('bold'),

            TextColumn::make('created_at')
                ->label('تاريخ الطلب')
                ->dateTime('d M Y - H:i')
                ->sortable(),
        ])
        ->filters([
            Filter::make('created_at')
                ->form([
                    DatePicker::make('created_from')->label('من تاريخ'),
                    DatePicker::make('created_until')->label('إلى تاريخ'),
                ])
                ->query(function ($query, array $data) {
                    return $query
                        ->when($data['created_from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['created_until'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                }),

            SelectFilter::make('status')
                ->label('حالة الطلب')
                ->options(\App\Enums\OrderStatus::class)
                ->multiple(),

            SelectFilter::make('payment_status')
                ->label('حالة الدفع')
                ->options(\App\Enums\PaymentStatus::class),

            SelectFilter::make('pos_status')
                ->label('مزامنة POS')
                ->options([
                    'failed' => 'فشل الإرسال (مراجعة مطلوبة)',
                    'sent' => 'تم الإرسال بنجاح',
                    'pending' => 'قيد الانتظار',
                ]),

            SelectFilter::make('branch')
                ->label('الفرع')
                ->relationship('branch', 'name')
                ->searchable()
                ->preload(),
        ]);
}
```

---

### B. Products Table ([ProductsTable.php](file:///e:/turbo_tenant/app/Plugins/CatalogProducts/src/Filament/Resources/Products/Tables/ProductsTable.php))

```php
public static function configure(Table $table): Table
{
    return $table
        ->defaultSort('created_at', 'desc')
        ->columns([
            ImageColumn::make('image')
                ->label('الصورة')
                ->square()
                ->defaultImageUrl(url('/images/placeholder.png')),

            TextColumn::make('name')
                ->label('الاسم')
                ->searchable()
                ->sortable(),

            TextColumn::make('name_ar')
                ->label('الاسم بالعربية')
                ->searchable()
                ->sortable()
                ->toggleable(),

            TextColumn::make('category.name')
                ->label('الفئة')
                ->searchable()
                ->sortable(),

            TextColumn::make('base_price')
                ->label('السعر الأساسي')
                ->money(fn () => app(\App\Plugins\StoreSettings\src\Services\SettingService::class)->get('currency', 'EGP'))
                ->sortable(),

            TextColumn::make('price_after_discount')
                ->label('السعر بعد الخصم')
                ->money(fn () => app(\App\Plugins\StoreSettings\src\Services\SettingService::class)->get('currency', 'EGP'))
                ->sortable()
                ->description(fn ($record) => $record->price_after_discount && $record->base_price 
                    ? '-' . round((($record->base_price - $record->price_after_discount) / $record->base_price) * 100) . '%'
                    : null
                ),

            TextColumn::make('variants_count')
                ->counts('variants')
                ->label('التنويعات')
                ->badge()
                ->color('info')
                ->sortable(),

            ToggleColumn::make('is_active')
                ->label('نشط')
                ->sortable(),
        ])
        ->filters([
            Filter::make('on_sale')
                ->label('منتجات عليها تخفيض')
                ->query(fn ($query) => $query->whereNotNull('price_after_discount')->whereColumn('price_after_discount', '<', 'base_price')),

            Filter::make('has_variants')
                ->label('منتجات بتنويعات متعددة')
                ->query(fn ($query) => $query->has('variants')),

            Filter::make('missing_image')
                ->label('منتجات بلا صورة')
                ->query(fn ($query) => $query->whereNull('image')->orWhere('image', '')),

            SelectFilter::make('category')
                ->label('الفئة')
                ->relationship('category', 'name')
                ->searchable()
                ->preload(),
        ]);
}
```

---

### C. Extra Options Table ([ExtraOptionsTable.php](file:///e:/turbo_tenant/app/Plugins/CatalogProducts/src/Filament/Resources/ExtraOptions/Tables/ExtraOptionsTable.php))

```php
public static function configure(Table $table): Table
{
    return $table
        ->defaultSort('created_at', 'desc')
        ->columns([
            TextColumn::make('name')
                ->label('اسم المجموعة')
                ->searchable()
                ->sortable(),

            TextColumn::make('name_ar')
                ->label('الاسم بالعربية')
                ->searchable()
                ->toggleable(),

            TextColumn::make('selection_rules')
                ->label('قواعد الاختيار')
                ->state(fn ($record) => match (true) {
                    $record->min_selections > 0 && $record->max_selections => "إجباري ({$record->min_selections} إلى {$record->max_selections})",
                    $record->min_selections > 0 => "إجباري (حد أدنى {$record->min_selections})",
                    $record->max_selections > 0 => "اختياري (أقصى حد {$record->max_selections})",
                    default => 'اختياري مفتوح',
                })
                ->badge()
                ->color(fn ($record) => $record->min_selections > 0 ? 'warning' : 'gray'),

            IconColumn::make('allow_multiple')
                ->label('تكرار الخيار')
                ->boolean(),

            TextColumn::make('items_count')
                ->counts('items')
                ->label('عناصر الإضافة')
                ->badge(),

            TextColumn::make('products_count')
                ->counts('products')
                ->label('المنتجات المرتبطة')
                ->badge()
                ->color('info'),

            ToggleColumn::make('is_active')
                ->label('نشط')
                ->sortable(),
        ])
        ->filters([
            TernaryFilter::make('is_mandatory')
                ->label('نوع الإلزام')
                ->queries(
                    true: fn ($query) => $query->where('min_selections', '>', 0),
                    false: fn ($query) => $query->where('min_selections', 0),
                )
                ->trueLabel('إجباري فقط')
                ->falseLabel('اختياري فقط'),
            TernaryFilter::make('is_active')
                ->label('الحالة'),
        ]);
}
```

---

### D. Reviews Moderation Table ([ReviewResource.php](file:///e:/turbo_tenant/app/Plugins/ProductReviews/src/Filament/Resources/ReviewResource.php))

```php
public static function table(Table $table): Table
{
    return $table
        ->defaultSort('created_at', 'desc')
        ->columns([
            TextColumn::make('product.name')
                ->label('المنتج')
                ->searchable()
                ->sortable(),

            TextColumn::make('author_name')
                ->label('صاحب التقييم')
                ->searchable(['guest_name', 'user.name']),

            TextColumn::make('rating')
                ->label('التقييم')
                ->formatStateUsing(fn (int $state): string => str_repeat('⭐', $state) . " ({$state}/5)")
                ->sortable(),

            TextColumn::make('comment')
                ->label('نص التعليق')
                ->limit(60)
                ->searchable(),

            IconColumn::make('is_approved')
                ->label('معتمد')
                ->boolean()
                ->sortable(),

            ToggleColumn::make('is_published')
                ->label('منشور'),

            TextColumn::make('created_at')
                ->label('تاريخ الإرسال')
                ->dateTime('d M Y - H:i')
                ->sortable(),
        ])
        ->filters([
            TernaryFilter::make('is_approved')
                ->label('حالة الاعتماد')
                ->trueLabel('معتمد')
                ->falseLabel('معلق (بانتظار المراجعة)')
                ->native(false),

            SelectFilter::make('rating')
                ->label('عدد النجوم')
                ->options([
                    5 => '⭐⭐⭐⭐⭐ (5 نجوم)',
                    4 => '⭐⭐⭐⭐ (4 نجوم)',
                    3 => '⭐⭐⭐ (3 نجوم)',
                    2 => '⭐⭐ (نجمتان)',
                    1 => '⭐ (نجمة واحدة)',
                ]),

            SelectFilter::make('product_id')
                ->label('المنتج')
                ->relationship('product', 'name')
                ->searchable()
                ->preload(),
        ])
        ->actions([
            Action::make('approve')
                ->label('اعتماد')
                ->icon('heroicon-o-check')
                ->color('success')
                ->visible(fn ($record) => ! $record->is_approved)
                ->action(fn ($record) => $record->update(['is_approved' => true])),

            Action::make('reject')
                ->label('حجب')
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->visible(fn ($record) => $record->is_approved)
                ->action(fn ($record) => $record->update(['is_approved' => false])),

            DeleteAction::make(),
        ]);
}
```

---

## 3. Verification Criteria

- [ ] Every table across all 18 resources has a designated `defaultSort()` preventing un-ordered SQL results.
- [ ] Categories, Sections, Variants, Governorates, Areas, and Sliders support drag-and-drop row reordering (`->reorderable('sort_order')`).
- [ ] Orders table displays customer phone numbers and payment badges with working date/payment filters.
- [ ] Extra Options table exposes `min_selections`, `max_selections`, `allow_multiple`, and linked `products_count`.
- [ ] Reviews table displays approval status with working 1-click approve/reject actions and star badges.
