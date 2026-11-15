<?php

declare(strict_types=1);

namespace App\Plugins\PaymentProcessing\src\Settings;

use App\Core\Contracts\SettingDefinition;
use App\Plugins\PaymentProcessing\src\Services\GatewayRegistry;
use App\Plugins\PaymentProcessing\src\Services\KashierService;
use App\Plugins\PaymentProcessing\src\Services\PaymobService;
use App\Plugins\StoreSettings\src\Contracts\SettingsSection;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;

final class PaymentSettingsSection implements SettingsSection
{
    public function __construct(
        private readonly GatewayRegistry $gatewayRegistry,
    ) {}

    public function pluginId(): string
    {
        return 'payment-processing';
    }

    public function sort(): int
    {
        return 30;
    }

    public function tab(): Tab
    {
        return Tab::make('المالية وبوابات الدفع')
            ->icon('heroicon-o-banknotes')
            ->schema([
                Section::make('رسوم الدفع والخيارات العامة')->schema([
                    Grid::make(2)->schema([
                        TextInput::make(PaymentSettingKey::COD_FEE->value)
                            ->label('رسوم الدفع عند الاستلام (COD)')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->prefix('EGP'),
                        Toggle::make(PaymentSettingKey::ONLINE_PAYMENTS_ENABLED->value)
                            ->label('تفعيل الدفع الإلكتروني عبر البطاقات البنكية والمحافظ')
                            ->helperText('السماح للعملاء بالدفع أونلاين عبر البطاقات البنكية أو المحافظ الإلكترونية')
                            ->default(true),
                    ]),
                ]),

                Section::make('إعدادات بوابات الدفع الإلكتروني')->schema([
                    Select::make(PaymentSettingKey::ACTIVE_PAYMENT_GATEWAY->value)
                        ->label('بوابة الدفع المفعلة')
                        ->options($this->gatewayRegistry->available())
                        ->default(PaymentSettingKey::ACTIVE_PAYMENT_GATEWAY->defaultValue())
                        ->required()
                        ->live()
                        ->helperText('اختر بوابة الدفع المعتمدة لمعالجة المدفوعات أونلاين'),

                    // Paymob Section
                    Section::make('بيانات الربط مع Paymob')
                        ->description('إعداد بيانات ومفاتيح بوابة الدفع Paymob')
                        ->visible(fn ($get): bool => $get(PaymentSettingKey::ACTIVE_PAYMENT_GATEWAY->value) === PaymobService::ID)
                        ->schema([
                            Grid::make(2)->schema([
                                TextInput::make(PaymentSettingKey::PAYMOB_BASE_URL->value)
                                    ->label('الرابط الأساسي (Base URL)')
                                    ->url()
                                    ->required()
                                    ->maxLength(255)
                                    ->default('https://accept.paymob.com'),
                                TextInput::make(PaymentSettingKey::PAYMOB_PUBLIC_KEY->value)
                                    ->label('المفتاح العام (Public Key)')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make(PaymentSettingKey::PAYMOB_SECRET_KEY->value)
                                    ->label('المفتاح السري (Secret Key)')
                                    ->password()
                                    ->revealable()
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make(PaymentSettingKey::PAYMOB_INTEGRATION_IDS->value)
                                    ->label('معرفات الدمج (Integration IDs)')
                                    ->helperText('معرفات مفصولة بفواصل (Comma separated IDs)')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make(PaymentSettingKey::PAYMOB_HMAC_SECRET->value)
                                    ->label('مفتاح HMAC السري (HMAC Secret)')
                                    ->password()
                                    ->revealable()
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make(PaymentSettingKey::PAYMOB_CURRENCY->value)
                                    ->label('عملة الحساب (Currency)')
                                    ->required()
                                    ->maxLength(10)
                                    ->default('EGP'),
                                TextInput::make(PaymentSettingKey::PAYMOB_MODE->value)
                                    ->label('وضع التشغيل (Mode)')
                                    ->required()
                                    ->maxLength(255)
                                    ->default('test'),
                            ]),
                        ]),

                    // Kashier Section
                    Section::make('بيانات الربط مع Kashier')
                        ->description('إعداد بيانات ومفاتيح بوابة الدفع Kashier')
                        ->visible(fn ($get): bool => $get(PaymentSettingKey::ACTIVE_PAYMENT_GATEWAY->value) === KashierService::ID)
                        ->schema([
                            Grid::make(2)->schema([
                                TextInput::make(PaymentSettingKey::KASHIER_MERCHANT_ID->value)
                                    ->label('معرف التاجر (Merchant ID)')
                                    ->required()
                                    ->maxLength(255)
                                    ->helperText('معرف التاجر الخاص بك في كاشير'),
                                TextInput::make(PaymentSettingKey::KASHIER_API_KEY->value)
                                    ->label('مفتاح API العام (API Key)')
                                    ->password()
                                    ->revealable()
                                    ->required()
                                    ->maxLength(255)
                                    ->helperText('يستخدم لتوليد التوقيع والتحقق منه'),
                                TextInput::make(PaymentSettingKey::KASHIER_SECRET_KEY->value)
                                    ->label('المفتاح السري (Secret Key)')
                                    ->password()
                                    ->revealable()
                                    ->required()
                                    ->maxLength(255)
                                    ->helperText('يستخدم لعمليات الاسترجاع وتفويض الـ API'),
                                Select::make(PaymentSettingKey::KASHIER_MODE->value)
                                    ->label('وضع التشغيل')
                                    ->options([
                                        'test' => 'وضع التجربة (Test Mode)',
                                        'live' => 'الوضع الفعلي (Live Mode)',
                                    ])
                                    ->default('test')
                                    ->required(),
                                TextInput::make(PaymentSettingKey::KASHIER_CURRENCY->value)
                                    ->label('العملة')
                                    ->required()
                                    ->maxLength(10)
                                    ->default('EGP'),
                                Select::make(PaymentSettingKey::KASHIER_ALLOWED_METHODS->value)
                                    ->label('طرق الدفع المسموحة')
                                    ->options([
                                        'card' => 'البطاقات البنكية فقط',
                                        'wallet' => 'المحافظ الإلكترونية فقط',
                                        'card,wallet' => 'البطاقات والمحافظ معاً',
                                    ])
                                    ->default('card')
                                    ->required()
                                    ->helperText('طرق الدفع المعروضة في صفحة إتمام الطلب'),
                            ]),
                        ]),
                ]),
            ]);
    }

    /**
     * @return list<SettingDefinition>
     */
    public function keys(): array
    {
        return [
            PaymentSettingKey::COD_FEE,
            PaymentSettingKey::ONLINE_PAYMENTS_ENABLED,
            PaymentSettingKey::ACTIVE_PAYMENT_GATEWAY,
            PaymentSettingKey::PAYMOB_BASE_URL,
            PaymentSettingKey::PAYMOB_PUBLIC_KEY,
            PaymentSettingKey::PAYMOB_SECRET_KEY,
            PaymentSettingKey::PAYMOB_INTEGRATION_IDS,
            PaymentSettingKey::PAYMOB_HMAC_SECRET,
            PaymentSettingKey::PAYMOB_CURRENCY,
            PaymentSettingKey::PAYMOB_MODE,
            PaymentSettingKey::KASHIER_MERCHANT_ID,
            PaymentSettingKey::KASHIER_API_KEY,
            PaymentSettingKey::KASHIER_SECRET_KEY,
            PaymentSettingKey::KASHIER_MODE,
            PaymentSettingKey::KASHIER_CURRENCY,
            PaymentSettingKey::KASHIER_ALLOWED_METHODS,
        ];
    }
}
