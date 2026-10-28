# Phase 5: Locations, Delivery & Customer CRM UX

> **Phase Target:** Quick Inline Creation, Shipping Fee Optimization, User CRM & Review Moderation  
> **Estimated Effort:** 1 hour  
> **Verification Command:** `php artisan test --filter=LocationTest`  

---

## 1. Objectives

1. **User Directive (Inline Governorate Creation):** Enable creating Governorates directly from inside [AreaForm.php](file:///e:/turbo_tenant/app/Plugins/LocationsDelivery/src/Filament/Resources/Areas/Schemas/AreaForm.php) via `createOptionForm`, eliminating context switching.
2. **Inline Shipping Cost Adjustment:** Allow instant delivery fee modification in [AreasTable.php](file:///e:/turbo_tenant/app/Plugins/LocationsDelivery/src/Filament/Resources/Areas/Tables/AreasTable.php).
3. **User CRM Missing Fields:** Add missing `phone` input and orders count to [UserForm.php](file:///e:/turbo_tenant/app/Filament/Resources/Users/Schemas/UserForm.php) and [UsersTable.php](file:///e:/turbo_tenant/app/Filament/Resources/Users/Tables/UsersTable.php).
4. **Review Moderation Speed:** Expose `is_approved` in [ReviewResource.php](file:///e:/turbo_tenant/app/Plugins/ProductReviews/src/Filament/Resources/ReviewResource.php), add 1-click approve/reject table actions, and convert numeric ratings into visual star badges.

---

## 2. Inline Governorate Creation in Area Form

In [AreaForm.php](file:///e:/turbo_tenant/app/Plugins/LocationsDelivery/src/Filament/Resources/Areas/Schemas/AreaForm.php):

Enable quick inline creation on the `governorate_id` select component:

```php
Select::make('governorate_id')
    ->label('المحافظة')
    ->relationship('governorate', 'name')
    ->searchable()
    ->preload()
    ->required()
    ->createOptionForm([
        TextInput::make('name')
            ->label('اسم المحافظة (إنجليزي)')
            ->required()
            ->maxLength(255),
        TextInput::make('name_ar')
            ->label('اسم المحافظة (عربي)')
            ->required()
            ->maxLength(255),
        TextInput::make('sort_order')
            ->label('ترتيب العرض')
            ->numeric()
            ->default(0),
        Toggle::make('is_active')
            ->label('نشطة للتوصيل')
            ->default(true),
    ])
    ->createOptionModalHeading('إضافة محافظة جديدة سريعة')
```

---

## 3. Areas Table: Inline Shipping Cost Adjustment

In [AreasTable.php](file:///e:/turbo_tenant/app/Plugins/LocationsDelivery/src/Filament/Resources/Areas/Tables/AreasTable.php):

Upgrade `shipping_cost` to support instant inline editing without having to open the full edit page:

```php
TextInputColumn::make('shipping_cost')
    ->label('تكلفة الشحن')
    ->rules(['numeric', 'min:0'])
    ->sortable()
```

---

## 4. User CRM: Phone Number & Order History

### A. User Form ([UserForm.php](file:///e:/turbo_tenant/app/Filament/Resources/Users/Schemas/UserForm.php))
Add the missing `phone` field and wrap the form in a clean section:

```php
Section::make('بيانات العميل الحسابية')
    ->schema([
        TextInput::make('name')
            ->label('الاسم الكامل')
            ->required()
            ->maxLength(255),
        TextInput::make('email')
            ->label('البريد الإلكتروني')
            ->email()
            ->required()
            ->unique(ignoreRecord: true),
        TextInput::make('phone')
            ->label('رقم الهاتف')
            ->tel()
            ->required()
            ->maxLength(20),
        TextInput::make('password')
            ->label('كلمة المرور')
            ->password()
            ->dehydrated(fn ($state) => filled($state))
            ->required(fn ($operation) => $operation === 'create'),
    ])
    ->columns(2)
```

### B. Users Table ([UsersTable.php](file:///e:/turbo_tenant/app/Filament/Resources/Users/Tables/UsersTable.php))
- Add `TextColumn::make('phone')->label('رقم الهاتف')->searchable()->copyable()`.
- Add `TextColumn::make('orders_count')->counts('orders')->label('عدد الطلبات')->badge()->color('success')->sortable()`.

---

## 5. Product Reviews: 1-Click Moderation & Visual Stars

In [ReviewResource.php](file:///e:/turbo_tenant/app/Plugins/ProductReviews/src/Filament/Resources/ReviewResource.php):

### A. Visual Stars & Missing Status
```php
TextColumn::make('rating')
    ->label('التقييم')
    ->formatStateUsing(fn (int $state): string => str_repeat('⭐', $state) . " ({$state}/5)")
    ->sortable(),

IconColumn::make('is_approved')
    ->label('معتمد')
    ->boolean()
    ->sortable(),
```

### B. 1-Click Approval Actions
```php
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
])
```

---

## 6. Verification & Acceptance Criteria

- [ ] Creating an Area allows clicking the "+" icon next to Governorate and creating a new governorate inside a modal without leaving the page.
- [ ] Shipping fee can be updated inline directly from the Areas table.
- [ ] Users form and table clearly display customer phone numbers and order counts.
- [ ] Reviews table shows approval status and allows 1-click approval/rejection.
