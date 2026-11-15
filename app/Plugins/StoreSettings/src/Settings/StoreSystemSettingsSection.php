<?php

declare(strict_types=1);

namespace App\Plugins\StoreSettings\src\Settings;

use App\Core\Contracts\SettingDefinition;
use App\Enums\SettingKey;
use App\Plugins\StoreSettings\src\Contracts\SettingsSection;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;

final class StoreSystemSettingsSection implements SettingsSection
{
    public function pluginId(): string
    {
        return 'store-settings';
    }

    public function sort(): int
    {
        return 50;
    }

    public function tab(): Tab
    {
        return Tab::make('النظام وساعات العمل')
            ->icon('heroicon-o-cpu-chip')
            ->schema([
                Section::make('إعدادات النظام والتشغيل')->schema([
                    Toggle::make(SettingKey::MAINTENANCE_MODE->value)
                        ->label('وضع الصيانة (إيقاف واجهة المتجر مؤقتاً)')
                        ->helperText('عند التفعيل، لن يتمكن العملاء من تصفح المتجر أو إرسال طلبات جديدة')
                        ->default(false),
                    Toggle::make(SettingKey::ACCEPT_ORDERS_AFTER_WORK_TIMES->value)
                        ->label('استقبال الطلبات خارج أوقات العمل الرسمية')
                        ->helperText('السماح للعملاء بإرسال الطلبات حتى وإن كان المطعم أو المتجر مغلقاً حالياً')
                        ->default(true),
                ]),

                Section::make('أوقات وساعات العمل')->schema([
                    Repeater::make(SettingKey::WORK_TIMES->value)
                        ->label('')
                        ->schema([
                            Select::make('day')
                                ->label('اليوم')
                                ->options([
                                    'saturday' => 'السبت (Saturday)',
                                    'sunday' => 'الأحد (Sunday)',
                                    'monday' => 'الاثنين (Monday)',
                                    'tuesday' => 'الثلاثاء (Tuesday)',
                                    'wednesday' => 'الأربعاء (Wednesday)',
                                    'thursday' => 'الخميس (Thursday)',
                                    'friday' => 'الجمعة (Friday)',
                                    'Saturday' => 'السبت (Saturday)',
                                    'Sunday' => 'الأحد (Sunday)',
                                    'Monday' => 'الاثنين (Monday)',
                                    'Tuesday' => 'الثلاثاء (Tuesday)',
                                    'Wednesday' => 'الأربعاء (Wednesday)',
                                    'Thursday' => 'الخميس (Thursday)',
                                    'Friday' => 'الجمعة (Friday)',
                                ])
                                ->required(),
                            TimePicker::make('from')
                                ->label('من الساعة')
                                ->required(),
                            TimePicker::make('to')
                                ->label('إلى الساعة')
                                ->required(),
                            Toggle::make('closed')
                                ->label('مغلق طوال اليوم')
                                ->default(false),
                        ])
                        ->columns(4)
                        ->defaultItems(7)
                        ->collapsible()
                        ->addActionLabel('إضافة يوم عمل'),
                ]),

                Section::make('الربط مع Google')->schema([
                    Grid::make(2)->schema([
                        TextInput::make(SettingKey::GOOGLE_CLIENT_ID->value)
                            ->label('معرف عميل Google (Client ID)')
                            ->password()
                            ->revealable()
                            ->maxLength(255),
                        TextInput::make(SettingKey::GOOGLE_CLIENT_SECRET->value)
                            ->label('المفتاح السري لـ Google (Client Secret)')
                            ->password()
                            ->revealable()
                            ->maxLength(255),
                        TextInput::make(SettingKey::GOOGLE_REDIRECT_URL->value)
                            ->label('رابط إعادة التوجيه لـ Google (Redirect URL)')
                            ->url()
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),
                ]),

                Section::make('إعدادات البريد الإلكتروني (SMTP)')->schema([
                    Grid::make(2)->schema([
                        TextInput::make(SettingKey::MAIL_MAILER->value)
                            ->label('نوع خادم البريد (Mailer)')
                            ->required()
                            ->maxLength(255)
                            ->default('smtp'),
                        TextInput::make(SettingKey::MAIL_HOST->value)
                            ->label('مضيف خادم البريد (Host)')
                            ->required()
                            ->maxLength(255),
                        TextInput::make(SettingKey::MAIL_PORT->value)
                            ->label('منفذ خادم البريد (Port)')
                            ->numeric()
                            ->required()
                            ->maxLength(255),
                        TextInput::make(SettingKey::MAIL_USERNAME->value)
                            ->label('اسم المستخدم للبريد (Username)')
                            ->maxLength(255),
                        TextInput::make(SettingKey::MAIL_PASSWORD->value)
                            ->label('كلمة مرور البريد (Password)')
                            ->password()
                            ->revealable()
                            ->maxLength(255),
                        TextInput::make(SettingKey::MAIL_ENCRYPTION->value)
                            ->label('نوع التشفير (Encryption)')
                            ->maxLength(255)
                            ->default('tls'),
                        TextInput::make(SettingKey::MAIL_FROM_ADDRESS->value)
                            ->label('عنوان المرسل (From Address)')
                            ->email()
                            ->required()
                            ->maxLength(255),
                        TextInput::make(SettingKey::MAIL_FROM_NAME->value)
                            ->label('اسم المرسل (From Name)')
                            ->required()
                            ->maxLength(255),
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
            SettingKey::MAINTENANCE_MODE,
            SettingKey::ACCEPT_ORDERS_AFTER_WORK_TIMES,
            SettingKey::WORK_TIMES,
            SettingKey::GOOGLE_CLIENT_ID,
            SettingKey::GOOGLE_CLIENT_SECRET,
            SettingKey::GOOGLE_REDIRECT_URL,
            SettingKey::MAIL_MAILER,
            SettingKey::MAIL_HOST,
            SettingKey::MAIL_PORT,
            SettingKey::MAIL_USERNAME,
            SettingKey::MAIL_PASSWORD,
            SettingKey::MAIL_ENCRYPTION,
            SettingKey::MAIL_FROM_ADDRESS,
            SettingKey::MAIL_FROM_NAME,
        ];
    }
}
