# Phase 3: Orders, Operations & Infolist Visibility

> **Phase Target:** Kitchen Customization Visibility, POS Authority Enforcement & Order Infolist UX  
> **Estimated Effort:** 1 hour  
> **Verification Command:** `php artisan test --filter=OrderTest`  

---

## 1. Objectives

1. **Expose Missing Item Customizations in Infolist:** Display all extra add-ons (toppings, sauces, sides) and weight quantities from `order_item_extras` inside [OrderInfolist.php](file:///e:/turbo_tenant/app/Plugins/OrderManagement/src/Filament/Resources/Orders/Schemas/OrderInfolist.php).
2. **User Directive (POS Status Authority):** Keep order status controlled by the POS system. Add a settings toggle (`allow_manual_order_status_update`) in Store Settings that enables admin status overrides only when explicitly activated.
3. **User Directive (Printing):** Exclude thermal receipt and kitchen invoice printing from Filament, delegating printing entirely to the POS terminal.
4. **Display Customer Phone:** Include clickable/copyable phone numbers in [OrdersTable.php](file:///e:/turbo_tenant/app/Plugins/OrderManagement/src/Filament/Resources/Orders/Tables/OrdersTable.php).
5. **Standardize Language & Currency:** Unify Arabic section headers and dynamic store currency throughout the order infolist.

---

## 2. Infolist: Rendering Item Add-ons & Extras

In [OrderInfolist.php](file:///e:/turbo_tenant/app/Plugins/OrderManagement/src/Filament/Resources/Orders/Schemas/OrderInfolist.php#L176-L196), upgrade the `Order Items` section to display nested extras:

```php
Section::make('عناصر ومحتويات الطلب')
    ->schema([
        RepeatableEntry::make('items')
            ->schema([
                Grid::make(5)->schema([
                    TextEntry::make('product_name')
                        ->label('المنتج')
                        ->weight('bold'),
                    TextEntry::make('variant_name')
                        ->label('التنويع')
                        ->placeholder('—'),
                    TextEntry::make('quantity')
                        ->label('الكمية')
                        ->numeric()
                        ->badge()
                        ->color('info'),
                    TextEntry::make('unit_price')
                        ->label('سعر الوحدة')
                        ->money(fn () => app(\App\Plugins\StoreSettings\src\Services\SettingService::class)->get('currency', 'EGP')),
                    TextEntry::make('total')
                        ->label('الإجمالي')
                        ->weight('bold')
                        ->money(fn () => app(\App\Plugins\StoreSettings\src\Services\SettingService::class)->get('currency', 'EGP')),
                ]),

                // CRITICAL: Display selected extras / customizations
                RepeatableEntry::make('extras')
                    ->label('الإضافات والتخصيصات المحددة')
                    ->visible(fn ($record) => $record && $record->extras()->exists())
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('extra_item_name')
                                ->label('الإضافة')
                                ->icon('heroicon-m-plus-circle'),
                            TextEntry::make('quantity')
                                ->label('الكمية')
                                ->formatStateUsing(fn ($state) => "x{$state}"),
                            TextEntry::make('price')
                                ->label('السعر الإضافي')
                                ->money(fn () => app(\App\Plugins\StoreSettings\src\Services\SettingService::class)->get('currency', 'EGP')),
                        ]),
                    ])
                    ->columnSpanFull(),

                TextEntry::make('notes')
                    ->label('ملاحظات الزبون على العنصر')
                    ->color('warning')
                    ->columnSpanFull()
                    ->visible(fn ($record) => filled($record?->notes)),
            ])
            ->columns(1),
    ])
    ->columnSpanFull()
```

---

## 3. Order Status Modification Strategy: POS vs. Manual Admin Override

### Setting Configuration
Add setting key in `SettingKey::ALLOW_MANUAL_ORDER_STATUS_UPDATE` with a default value of `false`:
- **When `false` (Default):** Order status changes are locked to the POS system. The Filament interface is read-only, preventing synchronization drift.
- **When `true` (Emergency Admin Override):** Filament reveals an action on the order view page allowing the store manager to update order status manually.

### Filament Action Implementation in [ViewOrder.php](file:///e:/turbo_tenant/app/Plugins/OrderManagement/src/Filament/Resources/Orders/Pages/ViewOrder.php):
```php
protected function getHeaderActions(): array
{
    $allowManualUpdate = (bool) app(\App\Plugins\StoreSettings\src\Services\SettingService::class)
        ->get('allow_manual_order_status_update', false);

    return [
        Action::make('updateStatus')
            ->label('تحديث حالة الطلب (يدوي)')
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->visible(fn () => $allowManualUpdate)
            ->form([
                Select::make('status')
                    ->label('الحالة الجديدة')
                    ->options(\App\Enums\OrderStatus::class)
                    ->required(),
                TextInput::make('reason')
                    ->label('سبب التعديل اليدوي')
                    ->required()
                    ->helperText('يتم تسجيل هذا التعديل لتدقيق الفروقات مع نظام POS'),
            ])
            ->action(function (array $data, \App\Plugins\OrderManagement\src\Models\Order $record): void {
                $record->update(['status' => $data['status']]);
                
                \Filament\Notifications\Notification::make()
                    ->title('تم تحديث حالة الطلب')
                    ->success()
                    ->send();
            }),
    ];
}
```

---

## 4. Orders Table Customer Phone Column

In [OrdersTable.php](file:///e:/turbo_tenant/app/Plugins/OrderManagement/src/Filament/Resources/Orders/Tables/OrdersTable.php), update the customer column to show the phone number clearly:

```php
TextColumn::make('customer')
    ->label('العميل')
    ->state(fn ($record) => $record->getCustomerName())
    ->description(fn ($record) => $record->getCustomerPhone() ?? '—')
    ->searchable(query: function ($query, $search) {
        return $query->where(function ($q) use ($search) {
            $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"))
              ->orWhereHas('guestUser', fn ($g) => $g->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"));
        });
    })
    ->copyable()
    ->copyMessage('تم نسخ الاسم')
```

---

## 5. Verification & Acceptance Criteria

- [ ] Viewing an order with extras clearly renders the exact add-ons and toppings chosen by the customer.
- [ ] Manual order status update button is hidden when `allow_manual_order_status_update` is false.
- [ ] Enabling `allow_manual_order_status_update` reveals the manual status override modal.
- [ ] Customer phone numbers appear under customer names in the orders table.
- [ ] Currency matches the tenant store configuration across both table and infolist.
