<?php

declare(strict_types=1);

namespace App\Plugins\StoreSettings\src\Filament\Pages;

use App\Enums\SettingKey;
use App\Plugins\StoreSettings\src\Services\SettingService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * @property-read Schema $form
 */
final class Settings extends Page
{
    use \App\Core\Plugins\Concerns\BelongsToPlugin;

    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'الإعدادات والنظام';

    protected static ?string $navigationLabel = 'إعدادات المتجر';

    protected static ?string $title = 'إعدادات المتجر الشاملة';

    protected string $view = 'filament.pages.settings';

    protected static ?int $navigationSort = 1;

    public function mount(): void
    {
        $settingService = app(SettingService::class);
        $data = $settingService->getAllAsArray();

        if (isset($data[SettingKey::PRODUCT_SHOW_CARDS->value])) {
            $cards = is_string($data[SettingKey::PRODUCT_SHOW_CARDS->value])
                ? json_decode($data[SettingKey::PRODUCT_SHOW_CARDS->value], true)
                : $data[SettingKey::PRODUCT_SHOW_CARDS->value];

            if (is_array($cards)) {
                foreach ($cards as &$card) {
                    if (isset($card['subtitle']) && ! isset($card['description'])) {
                        $card['description'] = $card['subtitle'];
                    }
                    if (isset($card['image']) && ! isset($card['icon'])) {
                        $card['icon'] = $card['image'];
                    }
                }
                $data[SettingKey::PRODUCT_SHOW_CARDS->value] = $cards;
            } else {
                $data[SettingKey::PRODUCT_SHOW_CARDS->value] = [];
            }
        }

        if (isset($data[SettingKey::WORK_TIMES->value])) {
            $workTimes = is_string($data[SettingKey::WORK_TIMES->value])
                ? json_decode($data[SettingKey::WORK_TIMES->value], true)
                : $data[SettingKey::WORK_TIMES->value];

            if (is_array($workTimes)) {
                foreach ($workTimes as &$time) {
                    if (isset($time['open_at']) && ! isset($time['from'])) {
                        $time['from'] = $time['open_at'];
                    }
                    if (isset($time['close_at']) && ! isset($time['to'])) {
                        $time['to'] = $time['close_at'];
                    }
                    if (isset($time['is_closed']) && ! isset($time['closed'])) {
                        $time['closed'] = (bool) $time['is_closed'];
                    }
                }
                $data[SettingKey::WORK_TIMES->value] = $workTimes;
            } else {
                $data[SettingKey::WORK_TIMES->value] = [];
            }
        }

        if (isset($data[SettingKey::SOCIAL_LINKS->value])) {
            $socialLinks = is_string($data[SettingKey::SOCIAL_LINKS->value])
                ? json_decode($data[SettingKey::SOCIAL_LINKS->value], true)
                : $data[SettingKey::SOCIAL_LINKS->value];

            $data[SettingKey::SOCIAL_LINKS->value] = is_array($socialLinks) ? $socialLinks : [];
        }

        if (isset($data[SettingKey::MAINTENANCE_MODE->value])) {
            $data[SettingKey::MAINTENANCE_MODE->value] = filter_var($data[SettingKey::MAINTENANCE_MODE->value], FILTER_VALIDATE_BOOLEAN);
        }

        if (isset($data[SettingKey::ACCEPT_ORDERS_AFTER_WORK_TIMES->value])) {
            $data[SettingKey::ACCEPT_ORDERS_AFTER_WORK_TIMES->value] = filter_var($data[SettingKey::ACCEPT_ORDERS_AFTER_WORK_TIMES->value], FILTER_VALIDATE_BOOLEAN);
        }

        if (isset($data[SettingKey::ONLINE_PAYMENTS_ENABLED->value])) {
            $data[SettingKey::ONLINE_PAYMENTS_ENABLED->value] = filter_var($data[SettingKey::ONLINE_PAYMENTS_ENABLED->value], FILTER_VALIDATE_BOOLEAN);
        }

        if (isset($data[SettingKey::ALLOW_MANUAL_ORDER_STATUS_UPDATE->value])) {
            $data[SettingKey::ALLOW_MANUAL_ORDER_STATUS_UPDATE->value] = filter_var($data[SettingKey::ALLOW_MANUAL_ORDER_STATUS_UPDATE->value], FILTER_VALIDATE_BOOLEAN);
        }

        $this->form->fill($data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    Tabs::make('إعدادات المتجر الشاملة')
                        ->tabs([
                            // TAB 1: BRANDING & GENERAL
                            Tab::make('الهوية والمتجر')
                                ->icon('heroicon-o-building-storefront')
                                ->schema([
                                    Grid::make(2)->schema([
                                        TextInput::make(SettingKey::SITE_NAME->value)
                                            ->label('اسم المتجر (إنجليزي)')
                                            ->required()
                                            ->maxLength(255),
                                        TextInput::make(SettingKey::SITE_NAME_AR->value)
                                            ->label('اسم المتجر (عربي)')
                                            ->maxLength(255),
                                        TextInput::make(SettingKey::CONTACT_EMAIL->value)
                                            ->label('البريد الإلكتروني للتواصل')
                                            ->email()
                                            ->maxLength(255),
                                        TextInput::make(SettingKey::CONTACT_PHONE->value)
                                            ->label('رقم هاتف التواصل')
                                            ->tel()
                                            ->maxLength(20),
                                        TextInput::make(SettingKey::CONTACT_ADDRESS->value)
                                            ->label('عنوان المقر الرئيسي')
                                            ->maxLength(255)
                                            ->columnSpanFull(),
                                        TextInput::make(SettingKey::SITE_DESCRIPTION->value)
                                            ->label('وصف المتجر المختصر')
                                            ->maxLength(500)
                                            ->columnSpanFull(),
                                        TextInput::make(SettingKey::FACEBOOK_APP_ID->value)
                                            ->label('معرف تطبيق فيسبوك (Facebook App ID)')
                                            ->maxLength(255)
                                            ->columnSpanFull(),
                                    ]),
                                    Section::make('الشعارات والوسائط')->schema([
                                        Grid::make(4)->schema([
                                            FileUpload::make(SettingKey::SITE_LOGO->value)
                                                ->label('شعار المتجر (Logo)')
                                                ->image()
                                                ->optimizeForWeb('site-images'),
                                            FileUpload::make(SettingKey::SITE_FAVICON->value)
                                                ->label('الأيقونة المفضلة (Favicon)')
                                                ->image()
                                                ->directory('images/site-images'),
                                            FileUpload::make(SettingKey::IMAGE_PLACEHOLDER->value)
                                                ->label('صورة بديلة للمنتجات (Placeholder)')
                                                ->image()
                                                ->optimizeForWeb('site-images'),
                                            FileUpload::make(SettingKey::SVG_LOGO->value)
                                                ->label('شعار المتجر (SVG Logo)')
                                                ->image()
                                                ->directory('images/site-images'),
                                        ]),
                                    ]),
                                    Section::make('روابط التواصل الاجتماعي')->schema([
                                        Repeater::make(SettingKey::SOCIAL_LINKS->value)
                                            ->label('')
                                            ->schema([
                                                Select::make('platform')
                                                    ->label('المنصة')
                                                    ->options([
                                                        'facebook' => 'فيسبوك (Facebook)',
                                                        'instagram' => 'انستغرام (Instagram)',
                                                        'twitter' => 'تويتر / X',
                                                        'tiktok' => 'تيك توك (TikTok)',
                                                        'youtube' => 'يوتيوب (YouTube)',
                                                        'linkedin' => 'لينكد إن (LinkedIn)',
                                                        'whatsapp' => 'واتساب (WhatsApp)',
                                                        'telegram' => 'تيليجرام (Telegram)',
                                                        'snapchat' => 'سناب شات (Snapchat)',
                                                        'pinterest' => 'بينترست (Pinterest)',
                                                        'other' => 'أخرى',
                                                    ])
                                                    ->required()
                                                    ->native(false),
                                                TextInput::make('url')
                                                    ->label('رابط الصفحة')
                                                    ->url()
                                                    ->required()
                                                    ->maxLength(255),
                                            ])
                                            ->columns(2)
                                            ->defaultItems(0)
                                            ->reorderable()
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
                                                ->maxLength(10)
                                                ->default('EGP'),
                                            TextInput::make(SettingKey::TAX_RATE->value)
                                                ->label('نسبة الضريبة المضافة (%)')
                                                ->numeric()
                                                ->suffix('%')
                                                ->minValue(0)
                                                ->maxValue(100)
                                                ->default(0),
                                            TextInput::make(SettingKey::COD_FEE->value)
                                                ->label('رسوم الدفع عند الاستلام (COD)')
                                                ->numeric()
                                                ->minValue(0)
                                                ->default(0)
                                                ->prefix('EGP'),
                                            TextInput::make(SettingKey::MIN_ORDER_AMOUNT->value)
                                                ->label('الحد الأدنى لقيمة الطلب')
                                                ->numeric()
                                                ->minValue(0)
                                                ->default(0)
                                                ->prefix('EGP'),
                                        ]),
                                    ]),

                                    Section::make('التحكم في أوامر الطلبات و POS')->schema([
                                        Toggle::make(SettingKey::ALLOW_MANUAL_ORDER_STATUS_UPDATE->value)
                                            ->label('السماح بالتعديل اليدوي لحالة الطلبات من لوحة الإدارة')
                                            ->helperText('ملاحظة: افتراضياً، يتم تحديث حالات الطلبات وتتبعها عبر جهاز نقطة البيع (POS). تفعيل هذا الخيار يسمح لمدير النظام بتجاوز نظام POS وتحديث الحالة يدوياً عند الضرورة.')
                                            ->default(false),
                                        Toggle::make(SettingKey::ONLINE_PAYMENTS_ENABLED->value)
                                            ->label('تفعيل الدفع الإلكتروني عبر البطاقات البنكية والمحافظ')
                                            ->helperText('السماح للعملاء بالدفع أونلاين عبر البطاقات البنكية أو المحافظ الإلكترونية')
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
                                            ->required()
                                            ->live()
                                            ->helperText('اختر بوابة الدفع المعتمدة لمعالجة المدفوعات أونلاين'),

                                        // Paymob Section
                                        Section::make('بيانات الربط مع Paymob')
                                            ->description('إعداد بيانات ومفاتيح بوابة الدفع Paymob')
                                            ->visible(fn ($get) => $get(SettingKey::ACTIVE_PAYMENT_GATEWAY->value) === 'paymob')
                                            ->schema([
                                                Grid::make(2)->schema([
                                                    TextInput::make(SettingKey::PAYMOB_BASE_URL->value)
                                                        ->label('الرابط الأساسي (Base URL)')
                                                        ->url()
                                                        ->required()
                                                        ->maxLength(255)
                                                        ->default('https://accept.paymob.com'),
                                                    TextInput::make(SettingKey::PAYMOB_PUBLIC_KEY->value)
                                                        ->label('المفتاح العام (Public Key)')
                                                        ->required()
                                                        ->maxLength(255),
                                                    TextInput::make(SettingKey::PAYMOB_SECRET_KEY->value)
                                                        ->label('المفتاح السري (Secret Key)')
                                                        ->password()
                                                        ->revealable()
                                                        ->required()
                                                        ->maxLength(255),
                                                    TextInput::make(SettingKey::PAYMOB_INTEGRATION_IDS->value)
                                                        ->label('معرفات الدمج (Integration IDs)')
                                                        ->helperText('معرفات مفصولة بفواصل (Comma separated IDs)')
                                                        ->required()
                                                        ->maxLength(255),
                                                    TextInput::make(SettingKey::PAYMOB_HMAC_SECRET->value)
                                                        ->label('مفتاح HMAC السري (HMAC Secret)')
                                                        ->password()
                                                        ->revealable()
                                                        ->required()
                                                        ->maxLength(255),
                                                    TextInput::make(SettingKey::PAYMOB_CURRENCY->value)
                                                        ->label('عملة الحساب (Currency)')
                                                        ->required()
                                                        ->maxLength(10)
                                                        ->default('EGP'),
                                                    TextInput::make(SettingKey::PAYMOB_MODE->value)
                                                        ->label('وضع التشغيل (Mode)')
                                                        ->required()
                                                        ->maxLength(255)
                                                        ->default('test'),
                                                ]),
                                            ]),

                                        // Kashier Section
                                        Section::make('بيانات الربط مع Kashier')
                                            ->description('إعداد بيانات ومفاتيح بوابة الدفع Kashier')
                                            ->visible(fn ($get) => $get(SettingKey::ACTIVE_PAYMENT_GATEWAY->value) === 'kashier')
                                            ->schema([
                                                Grid::make(2)->schema([
                                                    TextInput::make(SettingKey::KASHIER_MERCHANT_ID->value)
                                                        ->label('معرف التاجر (Merchant ID)')
                                                        ->required()
                                                        ->maxLength(255)
                                                        ->helperText('معرف التاجر الخاص بك في كاشير'),
                                                    TextInput::make(SettingKey::KASHIER_API_KEY->value)
                                                        ->label('مفتاح API العام (API Key)')
                                                        ->password()
                                                        ->revealable()
                                                        ->required()
                                                        ->maxLength(255)
                                                        ->helperText('يستخدم لتوليد التوقيع والتحقق منه'),
                                                    TextInput::make(SettingKey::KASHIER_SECRET_KEY->value)
                                                        ->label('المفتاح السري (Secret Key)')
                                                        ->password()
                                                        ->revealable()
                                                        ->required()
                                                        ->maxLength(255)
                                                        ->helperText('يستخدم لعمليات الاسترجاع وتفويض الـ API'),
                                                    Select::make(SettingKey::KASHIER_MODE->value)
                                                        ->label('وضع التشغيل')
                                                        ->options([
                                                            'test' => 'وضع التجربة (Test Mode)',
                                                            'live' => 'الوضع الفعلي (Live Mode)',
                                                        ])
                                                        ->default('test')
                                                        ->required(),
                                                    TextInput::make(SettingKey::KASHIER_CURRENCY->value)
                                                        ->label('العملة')
                                                        ->required()
                                                        ->maxLength(10)
                                                        ->default('EGP'),
                                                    Select::make(SettingKey::KASHIER_ALLOWED_METHODS->value)
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
                                ]),

                            // TAB 3: SYSTEM & HOURS
                            Tab::make('النظام وساعات العمل')
                                ->icon('heroicon-o-cpu-chip')
                                ->schema([
                                    Section::make('إعدادات النظام والتشغيل')->schema([
                                        Toggle::make(SettingKey::MAINTENANCE_MODE->value)
                                            ->label('وضع الصيانة (إيقاف واجهة المتجر مؤقتاً)')
                                            ->helperText('عند التفعيل، لن يتمكن العملاء من تصفح المتجر أو إرسال طلبات جديدة')
                                            ->default(false),
                                        TextInput::make(SettingKey::PRODUCTS_REPO_LINK->value)
                                            ->label('رابط مستودع المنتجات الرئيسي')
                                            ->url()
                                            ->maxLength(255)
                                            ->helperText('الرابط الأساسي لمستودع المنتجات المركزي (POS Products Repository URL)'),
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
                                ]),

                            // TAB 4: STOREFRONT CARDS
                            Tab::make('بطاقات الواجهة')
                                ->icon('heroicon-o-squares-plus')
                                ->schema([
                                    Repeater::make(SettingKey::PRODUCT_SHOW_CARDS->value)
                                        ->label('')
                                        ->schema([
                                            TextInput::make('title')
                                                ->label('عنوان البطاقة')
                                                ->required()
                                                ->maxLength(255),
                                            TextInput::make('description')
                                                ->label('الوصف الترويجي')
                                                ->required()
                                                ->maxLength(255),
                                            FileUpload::make('icon')
                                                ->label('أيقونة / صورة البطاقة')
                                                ->image()
                                                ->directory('product-cards')
                                                ->required(),
                                        ])
                                        ->columns(3)
                                        ->defaultItems(0)
                                        ->collapsible()
                                        ->addActionLabel('إضافة بطاقة جديدة'),
                                ]),
                        ])
                        ->columnSpanFull(),
                ])
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('حفظ الإعدادات')
                                ->submit('save')
                                ->keyBindings(['mod+s']),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $settingService = app(SettingService::class);

        if (isset($data[SettingKey::MAINTENANCE_MODE->value])) {
            $data[SettingKey::MAINTENANCE_MODE->value] = $data[SettingKey::MAINTENANCE_MODE->value] ? 'true' : 'false';
        }

        if (isset($data[SettingKey::ACCEPT_ORDERS_AFTER_WORK_TIMES->value])) {
            $data[SettingKey::ACCEPT_ORDERS_AFTER_WORK_TIMES->value] = $data[SettingKey::ACCEPT_ORDERS_AFTER_WORK_TIMES->value] ? 'true' : 'false';
        }

        if (isset($data[SettingKey::ONLINE_PAYMENTS_ENABLED->value])) {
            $data[SettingKey::ONLINE_PAYMENTS_ENABLED->value] = $data[SettingKey::ONLINE_PAYMENTS_ENABLED->value] ? 'true' : 'false';
        }

        if (isset($data[SettingKey::ALLOW_MANUAL_ORDER_STATUS_UPDATE->value])) {
            $data[SettingKey::ALLOW_MANUAL_ORDER_STATUS_UPDATE->value] = $data[SettingKey::ALLOW_MANUAL_ORDER_STATUS_UPDATE->value] ? 'true' : 'false';
        }

        if (isset($data[SettingKey::PRODUCT_SHOW_CARDS->value]) && is_array($data[SettingKey::PRODUCT_SHOW_CARDS->value])) {
            $normalizedCards = [];
            foreach ($data[SettingKey::PRODUCT_SHOW_CARDS->value] as $card) {
                $normalizedCards[] = array_merge($card, [
                    'subtitle' => $card['description'] ?? $card['subtitle'] ?? '',
                    'image' => $card['icon'] ?? $card['image'] ?? '',
                ]);
            }
            $data[SettingKey::PRODUCT_SHOW_CARDS->value] = json_encode(array_values($normalizedCards));
        }

        if (isset($data[SettingKey::WORK_TIMES->value]) && is_array($data[SettingKey::WORK_TIMES->value])) {
            $normalizedTimes = [];
            foreach ($data[SettingKey::WORK_TIMES->value] as $item) {
                $normalizedTimes[] = array_merge($item, [
                    'open_at' => $item['from'] ?? $item['open_at'] ?? null,
                    'close_at' => $item['to'] ?? $item['close_at'] ?? null,
                    'is_closed' => ! empty($item['closed']),
                ]);
            }
            $data[SettingKey::WORK_TIMES->value] = json_encode(array_values($normalizedTimes));
        }

        if (isset($data[SettingKey::SOCIAL_LINKS->value]) && is_array($data[SettingKey::SOCIAL_LINKS->value])) {
            $data[SettingKey::SOCIAL_LINKS->value] = json_encode(array_values($data[SettingKey::SOCIAL_LINKS->value]));
        }

        $settingService->setMultiple($data);

        Notification::make()
            ->success()
            ->title('تم الحفظ بنجاح')
            ->body('تم تحديث إعدادات المتجر بنجاح.')
            ->send();
    }
}
