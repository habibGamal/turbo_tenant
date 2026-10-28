# Phase 6: Store Settings Restructuring & Full System Verification

> **Phase Target:** 4-Tab Store Settings Overhaul, POS Setting Integration & End-to-End Audit  
> **Estimated Effort:** 1.5 hours  
> **Verification Command:** `php artisan test && ./vendor/bin/pint --test`  

---

## 1. Objectives

1. Refactor the monolithic 465-line [Settings.php](file:///e:/turbo_tenant/app/Plugins/StoreSettings/src/Filament/Pages/Settings.php) into **4 organized Arabic Tabs**.
2. **User Directive Implementation:** Add the `allow_manual_order_status_update` setting toggle in the Financial & Operations tab to control POS status ownership vs. manual Filament overrides.
3. Translate all setting section headers, labels, and helper texts to Arabic for complete panel consistency.
4. Execute full automated testing and linting to guarantee zero regressions.

---

## 2. Store Settings 4-Tab Restructuring

Refactor `form()` in [Settings.php](file:///e:/turbo_tenant/app/Plugins/StoreSettings/src/Filament/Pages/Settings.php):

```php
Tabs::make('إعدادات المتجر الشاملة')
    ->tabs([
        // TAB 1: BRANDING & GENERAL
        Tab::make('الهوية والمتجر')
            ->icon('heroicon-o-building-storefront')
            ->schema([
                Grid::make(2)->schema([
                    TextInput::make(SettingKey::SITE_NAME->value)
                        ->label('اسم المتجر (إنجليزي)')
                        ->required(),
                    TextInput::make(SettingKey::SITE_NAME_AR->value)
                        ->label('اسم المتجر (عربي)'),
                    TextInput::make(SettingKey::CONTACT_EMAIL->value)
                        ->label('البريد الإلكتروني للتواصل')
                        ->email(),
                    TextInput::make(SettingKey::CONTACT_PHONE->value)
                        ->label('رقم هاتف التواصل')
                        ->tel(),
                    TextInput::make(SettingKey::CONTACT_ADDRESS->value)
                        ->label('عنوان المقر الرئيسي')
                        ->columnSpanFull(),
                    TextInput::make(SettingKey::SITE_DESCRIPTION->value)
                        ->label('وصف المتجر المختصر')
                        ->columnSpanFull(),
                ]),
                Section::make('الشعارات والوسائط')->schema([
                    Grid::make(3)->schema([
                        FileUpload::make(SettingKey::SITE_LOGO->value)
                            ->label('شعار المتجر (Logo)')
                            ->image()
                            ->optimizeForWeb('site-images'),
                        FileUpload::make(SettingKey::SITE_FAVICON->value)
                            ->label('الأيقونة المفضلة (Favicon)')
                            ->image()
                            ->directory('images/site-images'),
                        FileUpload::make(SettingKey::IMAGE_PLACEHOLDER->value)
                            ->label('صورة بديلة للمنتجات')
                            ->image()
                            ->optimizeForWeb('site-images'),
                    ]),
                ]),
                Section::make('روابط التواصل الاجتماعي')->schema([
                    Repeater::make(SettingKey::SOCIAL_LINKS->value)
                        ->label('')
                        ->schema([
                            Select::make('platform')
                                ->label('المنصة')
                                ->options([
                                    'facebook' => 'فيسبوك',
                                    'instagram' => 'انستغرام',
                                    'twitter' => 'تويتر / X',
                                    'tiktok' => 'تيك توك',
                                    'whatsapp' => 'واتساب',
                                ])
                                ->required(),
                            TextInput::make('url')
                                ->label('رابط الصفحة')
                                ->url()
                                ->required(),
                        ])
                        ->columns(2)
                        ->collapsible()
                        ->addActionLabel('إضافة رابط منصة جديدة'),
                ]),
            ]),

        // TAB 2: FINANCIAL & PAYMENTS
        Tab::make('المالية وبوابات الدفع')
            ->icon('heroicon-o-banknotes')
            ->schema([
                Section::make('العملة والرسوم الأساسية')->schema([
                    Grid::make(2)->schema([
                        TextInput::make(SettingKey::CURRENCY->value)
                            ->label('رمز العملة الافتراضية')
                            ->required()
                            ->default('EGP'),
                        TextInput::make(SettingKey::TAX_RATE->value)
                            ->label('نسبة الضريبة المضافة (%)')
                            ->numeric()
                            ->suffix('%')
                            ->default(0),
                        TextInput::make(SettingKey::COD_FEE->value)
                            ->label('رسوم الدفع عند الاستلام (COD)')
                            ->numeric()
                            ->default(0),
                        TextInput::make(SettingKey::MIN_ORDER_AMOUNT->value)
                            ->label('الحد الأدنى لقيمة الطلب')
                            ->numeric()
                            ->default(0),
                    ]),
                ]),

                Section::make('التحكم في أوامر الطلبات و POS')->schema([
                    // USER DIRECTIVE TOGGLE: POS vs Manual Admin Override
                    Toggle::make('allow_manual_order_status_update')
                        ->label('السماح بالتعديل اليدوي لحالة الطلبات من لوحة الإدارة')
                        ->helperText('ملاحظة: افتراضياً، يتم تحديث حالات الطلبات وتتبعها عبر جهاز نقطة البيع (POS). تفعيل هذا الخيار يسمح لمدير النظام بتجاوز نظام POS وتحديث الحالة يدوياً عند الضرورة.')
                        ->default(false),
                    Toggle::make(SettingKey::ONLINE_PAYMENTS_ENABLED->value)
                        ->label('تفعيل الدفع الإلكتروني عبر البطاقات البنكية')
                        ->default(true),
                ]),

                Section::make('إعدادات بوابات الدفع الإلكتروني')->schema([
                    Select::make(SettingKey::ACTIVE_PAYMENT_GATEWAY->value)
                        ->label('بوابة الدفع المفعلة')
                        ->options([
                            'paymob' => 'Paymob (باي موب)',
                            'kashier' => 'Kashier (كاشير)',
                        ])
                        ->default('paymob')
                        ->live(),

                    // Paymob Credentials
                    Section::make('بيانات الربط مع Paymob')
                        ->visible(fn (callable $get) => $get(SettingKey::ACTIVE_PAYMENT_GATEWAY->value) === 'paymob')
                        ->schema([
                            TextInput::make(SettingKey::PAYMOB_PUBLIC_KEY->value)
                                ->label('المفتاح العام (Public Key)')
                                ->required(),
                            TextInput::make(SettingKey::PAYMOB_SECRET_KEY->value)
                                ->label('المفتاح السري (Secret Key)')
                                ->password()
                                ->revealable()
                                ->required(),
                        ]),
                ]),
            ]),

        // TAB 3: SYSTEM & HOURS
        Tab::make('النظام وساعات العمل')
            ->icon('heroicon-o-cpu-chip')
            ->schema([
                Toggle::make(SettingKey::MAINTENANCE_MODE->value)
                    ->label('وضع الصيانة (إيقاف واجهة المتجر مؤقتاً)')
                    ->helperText('عند التفعيل، لن يتمكن العملاء من تصفح المتجر أو إرسال طلبات جديدة'),
                Section::make('أوقات وساعات العمل')->schema([
                    Repeater::make(SettingKey::WORK_TIMES->value)
                        ->schema([
                            Select::make('day')
                                ->label('اليوم')
                                ->options([
                                    'saturday' => 'السبت',
                                    'sunday' => 'الأحد',
                                    'monday' => 'الاثنين',
                                    'tuesday' => 'الثلاثاء',
                                    'wednesday' => 'الأربعاء',
                                    'thursday' => 'الخميس',
                                    'friday' => 'الجمعة',
                                ]),
                            TimePicker::make('open_at')->label('من الساعة'),
                            TimePicker::make('close_at')->label('إلى الساعة'),
                            Toggle::make('is_closed')->label('مغلق طوال اليوم'),
                        ])
                        ->columns(4)
                        ->collapsible(),
                ]),
            ]),

        // TAB 4: STOREFRONT CARDS
        Tab::make('بطاقات الواجهة')
            ->icon('heroicon-o-squares-plus')
            ->schema([
                Repeater::make(SettingKey::PRODUCT_SHOW_CARDS->value)
                    ->schema([
                        TextInput::make('title')->label('عنوان البطاقة'),
                        TextInput::make('subtitle')->label('الوصف الترويجي'),
                        FileUpload::make('image')->label('صورة البطاقة')->image(),
                    ])
                    ->columns(3)
                    ->collapsible(),
            ]),
    ])
    ->columnSpanFull()
```

---

## 3. End-to-End Verification Checklist

| Checkpoint | Target | Expected Result |
| :--- | :--- | :--- |
| **1. Navigation Layout** | All 18 resources in panel | 7 distinct navigation groups, exact order, AddressResource hidden, Slider named "سلايدر". |
| **2. Enums & Badges** | Orders list & Infolist | `OrderStatus`, `OrderType`, `PaymentStatus` automatically render localized Arabic text and colored badges. |
| **3. Product Tabs & Extras** | Product edit page | 4 tabs render cleanly. Selection rules (`min/max_selections`) persist on both ExtraOption and Product. |
| **4. Order Customizations** | Order detail infolist | All customer extra choices, toppings, and weight options render accurately. |
| **5. POS Authority Switch** | Settings Financial Tab | `allow_manual_order_status_update` toggles the status change action in order view. |
| **6. Inline Creation** | Area create/edit form | Clicking "+" next to Governorate opens a modal and creates a governorate on the fly. |
| **7. Table Interactivity** | Products, Categories, Reviews | Status toggles flip instantly; reviews have 1-click approval buttons. |
| **8. Settings Reorganization** | Store Settings page | Renders as 4 responsive tabs in full Arabic without monolithic scrolling. |

---

## 4. Execution Commands

```bash
# Verify PHP syntax and types
php artisan test
./vendor/bin/pint --test

# Clear filament caches
php artisan filament:clear-cached-components
php artisan view:clear
php artisan cache:clear
```
