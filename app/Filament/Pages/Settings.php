<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\SettingKey;
use App\Services\SettingService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * @property-read Schema $form
 */
final class Settings extends Page
{
    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Settings';

    protected static ?string $title = 'Application Settings';

    protected string $view = 'filament.pages.settings';

    protected static ?int $navigationSort = 100;

    public function mount(): void
    {
        $settingService = app(SettingService::class);
        $data = $settingService->getAllAsArray();

        if (isset($data[SettingKey::PRODUCT_SHOW_CARDS->value])) {
            $data[SettingKey::PRODUCT_SHOW_CARDS->value] = json_decode($data[SettingKey::PRODUCT_SHOW_CARDS->value], true) ?? [];
        }

        if (isset($data[SettingKey::WORK_TIMES->value])) {
            $data[SettingKey::WORK_TIMES->value] = json_decode($data[SettingKey::WORK_TIMES->value], true) ?? [];
        }

        $this->form->fill($data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    Section::make('General Settings')
                        ->schema([
                            TextInput::make(SettingKey::SITE_NAME->value)
                                ->label(SettingKey::SITE_NAME->label())
                                ->required()
                                ->maxLength(255),
                            FileUpload::make(SettingKey::SITE_LOGO->value)
                                ->label(SettingKey::SITE_LOGO->label())
                                ->image()
                                ->directory('site-images'),
                            TextInput::make(SettingKey::SITE_DESCRIPTION->value)
                                ->label(SettingKey::SITE_DESCRIPTION->label())
                                ->maxLength(500),
                            TextInput::make(SettingKey::CONTACT_EMAIL->value)
                                ->label(SettingKey::CONTACT_EMAIL->label())
                                ->email()
                                ->maxLength(255),
                            TextInput::make(SettingKey::CONTACT_PHONE->value)
                                ->label(SettingKey::CONTACT_PHONE->label())
                                ->tel()
                                ->maxLength(20),
                            TextInput::make(SettingKey::CONTACT_ADDRESS->value)
                                ->label(SettingKey::CONTACT_ADDRESS->label())
                                ->maxLength(255),
                            TextInput::make(SettingKey::SOCIAL_FACEBOOK->value)
                                ->label(SettingKey::SOCIAL_FACEBOOK->label())
                                ->url()
                                ->maxLength(255),
                            TextInput::make(SettingKey::SOCIAL_INSTAGRAM->value)
                                ->label(SettingKey::SOCIAL_INSTAGRAM->label())
                                ->url()
                                ->maxLength(255),
                            TextInput::make(SettingKey::SOCIAL_TWITTER->value)
                                ->label(SettingKey::SOCIAL_TWITTER->label())
                                ->url()
                                ->maxLength(255),
                            FileUpload::make(SettingKey::SITE_FAVICON->value)
                                ->label(SettingKey::SITE_FAVICON->label())
                                ->image()
                                ->directory('site-images'),
                            FileUpload::make(SettingKey::IMAGE_PLACEHOLDER->value)
                                ->label(SettingKey::IMAGE_PLACEHOLDER->label())
                                ->image()
                                ->directory('site-images'),
                            FileUpload::make(SettingKey::SVG_LOGO->value)
                                ->label(SettingKey::SVG_LOGO->label())
                                ->image()
                                ->directory('site-images'),
                            TextInput::make(SettingKey::FACEBOOK_APP_ID->value)
                                ->label(SettingKey::FACEBOOK_APP_ID->label())
                                ->maxLength(255),
                        ])
                        ->columns(2),

                    Section::make('Financial Settings')
                        ->schema([
                            TextInput::make(SettingKey::CURRENCY->value)
                                ->label(SettingKey::CURRENCY->label())
                                ->required()
                                ->maxLength(10)
                                ->default('EGP'),
                            TextInput::make(SettingKey::TAX_RATE->value)
                                ->label(SettingKey::TAX_RATE->label())
                                ->numeric()
                                ->suffix('%')
                                ->minValue(0)
                                ->maxValue(100)
                                ->default(0),
                            TextInput::make(SettingKey::COD_FEE->value)
                                ->label(SettingKey::COD_FEE->label())
                                ->numeric()
                                ->minValue(0)
                                ->default(0)
                                ->prefix('EGP'),
                            TextInput::make(SettingKey::MIN_ORDER_AMOUNT->value)
                                ->label(SettingKey::MIN_ORDER_AMOUNT->label())
                                ->numeric()
                                ->minValue(0)
                                ->default(0)
                                ->prefix('EGP'),
                            Toggle::make(SettingKey::ONLINE_PAYMENTS_ENABLED->value)
                                ->label(SettingKey::ONLINE_PAYMENTS_ENABLED->label())
                                ->helperText('Allow customers to pay online with credit/debit cards')
                                ->default(true),
                        ])
                        ->columns(2),

                    Section::make('System Settings')
                        ->schema([
                            TextInput::make(SettingKey::PRODUCTS_REPO_LINK->value)
                                ->label(SettingKey::PRODUCTS_REPO_LINK->label())
                                ->url()
                                ->maxLength(255)
                                ->helperText('The base URL for the master products repository'),
                            Toggle::make(SettingKey::MAINTENANCE_MODE->value)
                                ->label(SettingKey::MAINTENANCE_MODE->label())
                                ->helperText('Enable maintenance mode to prevent customers from accessing the website')
                                ->default(false),
                        ]),

                    Section::make('Google Integration')
                        ->schema([
                            TextInput::make(SettingKey::GOOGLE_CLIENT_ID->value)
                                ->label(SettingKey::GOOGLE_CLIENT_ID->label())
                                ->password()
                                ->revealable()
                                ->maxLength(255),
                            TextInput::make(SettingKey::GOOGLE_CLIENT_SECRET->value)
                                ->label(SettingKey::GOOGLE_CLIENT_SECRET->label())
                                ->password()
                                ->revealable()
                                ->maxLength(255),
                            TextInput::make(SettingKey::GOOGLE_REDIRECT_URL->value)
                                ->label(SettingKey::GOOGLE_REDIRECT_URL->label())
                                ->url()
                                ->maxLength(255),
                        ])
                        ->columns(2),

                    Section::make('Payment Gateway Selection')
                        ->description('Choose which payment gateway to use for online payments')
                        ->schema([
                            \Filament\Forms\Components\Select::make(SettingKey::ACTIVE_PAYMENT_GATEWAY->value)
                                ->label(SettingKey::ACTIVE_PAYMENT_GATEWAY->label())
                                ->options([
                                    'paymob' => 'Paymob',
                                    'kashier' => 'Kashier',
                                ])
                                ->default('paymob')
                                ->required()
                                ->helperText('Select the payment gateway to use for processing online payments'),
                        ]),

                    Section::make('Payment Gateway (Paymob)')
                        ->description('Configure Paymob payment gateway credentials')
                        ->collapsed(fn ($get) => $get(SettingKey::ACTIVE_PAYMENT_GATEWAY->value) !== 'paymob')
                        ->schema([
                            TextInput::make(SettingKey::PAYMOB_BASE_URL->value)
                                ->label(SettingKey::PAYMOB_BASE_URL->label())
                                ->url()
                                ->required()
                                ->maxLength(255)
                                ->default('https://accept.paymob.com'),
                            TextInput::make(SettingKey::PAYMOB_PUBLIC_KEY->value)
                                ->label(SettingKey::PAYMOB_PUBLIC_KEY->label())
                                ->required()
                                ->maxLength(255),
                            TextInput::make(SettingKey::PAYMOB_SECRET_KEY->value)
                                ->label(SettingKey::PAYMOB_SECRET_KEY->label())
                                ->password()
                                ->revealable()
                                ->required()
                                ->maxLength(255),
                            TextInput::make(SettingKey::PAYMOB_INTEGRATION_IDS->value)
                                ->label(SettingKey::PAYMOB_INTEGRATION_IDS->label())
                                ->helperText('Comma separated IDs')
                                ->required()
                                ->maxLength(255),
                            TextInput::make(SettingKey::PAYMOB_HMAC_SECRET->value)
                                ->label(SettingKey::PAYMOB_HMAC_SECRET->label())
                                ->password()
                                ->revealable()
                                ->required()
                                ->maxLength(255),
                            TextInput::make(SettingKey::PAYMOB_CURRENCY->value)
                                ->label(SettingKey::PAYMOB_CURRENCY->label())
                                ->required()
                                ->maxLength(10)
                                ->default('EGP'),
                            TextInput::make(SettingKey::PAYMOB_MODE->value)
                                ->label(SettingKey::PAYMOB_MODE->label())
                                ->required()
                                ->maxLength(255)
                                ->default('test'),
                        ])
                        ->columns(2),

                    Section::make('Payment Gateway (Kashier)')
                        ->description('Configure Kashier payment gateway credentials')
                        ->collapsed(fn ($get) => $get(SettingKey::ACTIVE_PAYMENT_GATEWAY->value) !== 'kashier')
                        ->schema([
                            TextInput::make(SettingKey::KASHIER_MERCHANT_ID->value)
                                ->label(SettingKey::KASHIER_MERCHANT_ID->label())
                                ->required()
                                ->maxLength(255)
                                ->helperText('Your Kashier merchant ID'),
                            TextInput::make(SettingKey::KASHIER_API_KEY->value)
                                ->label(SettingKey::KASHIER_API_KEY->label())
                                ->password()
                                ->revealable()
                                ->required()
                                ->maxLength(255)
                                ->helperText('Used for hash generation and signature validation'),
                            TextInput::make(SettingKey::KASHIER_SECRET_KEY->value)
                                ->label(SettingKey::KASHIER_SECRET_KEY->label())
                                ->password()
                                ->revealable()
                                ->required()
                                ->maxLength(255)
                                ->helperText('Used for refund API authorization'),
                            \Filament\Forms\Components\Select::make(SettingKey::KASHIER_MODE->value)
                                ->label(SettingKey::KASHIER_MODE->label())
                                ->options([
                                    'test' => 'Test Mode',
                                    'live' => 'Live Mode',
                                ])
                                ->default('test')
                                ->required(),
                            TextInput::make(SettingKey::KASHIER_CURRENCY->value)
                                ->label(SettingKey::KASHIER_CURRENCY->label())
                                ->required()
                                ->maxLength(10)
                                ->default('EGP'),
                            \Filament\Forms\Components\Select::make(SettingKey::KASHIER_ALLOWED_METHODS->value)
                                ->label(SettingKey::KASHIER_ALLOWED_METHODS->label())
                                ->options([
                                    'card' => 'Credit/Debit Card Only',
                                    'wallet' => 'Mobile Wallet Only',
                                    'card,wallet' => 'Both Card and Wallet',
                                ])
                                ->default('card')
                                ->required()
                                ->helperText('Payment methods to show in checkout'),
                        ])
                        ->columns(2),

                    Section::make('Mail Settings')
                        ->schema([
                            TextInput::make(SettingKey::MAIL_MAILER->value)
                                ->label(SettingKey::MAIL_MAILER->label())
                                ->required()
                                ->maxLength(255)
                                ->default('smtp'),
                            TextInput::make(SettingKey::MAIL_HOST->value)
                                ->label(SettingKey::MAIL_HOST->label())
                                ->required()
                                ->maxLength(255),
                            TextInput::make(SettingKey::MAIL_PORT->value)
                                ->label(SettingKey::MAIL_PORT->label())
                                ->numeric()
                                ->required()
                                ->maxLength(255),
                            TextInput::make(SettingKey::MAIL_USERNAME->value)
                                ->label(SettingKey::MAIL_USERNAME->label())
                                ->maxLength(255),
                            TextInput::make(SettingKey::MAIL_PASSWORD->value)
                                ->label(SettingKey::MAIL_PASSWORD->label())
                                ->password()
                                ->revealable()
                                ->maxLength(255),
                            TextInput::make(SettingKey::MAIL_ENCRYPTION->value)
                                ->label(SettingKey::MAIL_ENCRYPTION->label())
                                ->maxLength(255)
                                ->default('tls'),
                            TextInput::make(SettingKey::MAIL_FROM_ADDRESS->value)
                                ->label(SettingKey::MAIL_FROM_ADDRESS->label())
                                ->email()
                                ->required()
                                ->maxLength(255),
                            TextInput::make(SettingKey::MAIL_FROM_NAME->value)
                                ->label(SettingKey::MAIL_FROM_NAME->label())
                                ->required()
                                ->maxLength(255),
                        ])
                        ->columns(2),

                    Section::make('Product Page Settings')
                        ->schema([
                            Repeater::make(SettingKey::PRODUCT_SHOW_CARDS->value)
                                ->label(SettingKey::PRODUCT_SHOW_CARDS->label())
                                ->schema([
                                    TextInput::make('title')
                                        ->required()
                                        ->maxLength(255),
                                    TextInput::make('description')
                                        ->required()
                                        ->maxLength(255),
                                    FileUpload::make('icon')
                                        ->image()
                                        ->directory('product-cards')
                                        ->required(),
                                ])
                                ->columns(3)
                                ->defaultItems(0)
                                ->addActionLabel('Add Card'),
                        ]),

                    Section::make('Work Times')
                        ->schema([
                            Toggle::make(SettingKey::ACCEPT_ORDERS_AFTER_WORK_TIMES->value)
                                ->label(SettingKey::ACCEPT_ORDERS_AFTER_WORK_TIMES->label())
                                ->default(true),
                            Repeater::make(SettingKey::WORK_TIMES->value)
                                ->label(SettingKey::WORK_TIMES->label())
                                ->schema([
                                    \Filament\Forms\Components\Select::make('day')
                                        ->options([
                                            'Sunday' => 'Sunday',
                                            'Monday' => 'Monday',
                                            'Tuesday' => 'Tuesday',
                                            'Wednesday' => 'Wednesday',
                                            'Thursday' => 'Thursday',
                                            'Friday' => 'Friday',
                                            'Saturday' => 'Saturday',
                                        ])
                                        ->required(),
                                    \Filament\Forms\Components\TimePicker::make('from')
                                        ->required(),
                                    \Filament\Forms\Components\TimePicker::make('to')
                                        ->required(),
                                    Toggle::make('closed')
                                        ->default(false),
                                ])
                                ->columns(4)
                                ->defaultItems(7)
                                ->addActionLabel('Add Day'),
                        ]),
                ])
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('Save Settings')
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

        // Convert boolean values to strings for maintenance mode
        if (isset($data[SettingKey::MAINTENANCE_MODE->value])) {
            $data[SettingKey::MAINTENANCE_MODE->value] = $data[SettingKey::MAINTENANCE_MODE->value] ? 'true' : 'false';
        }

        if (isset($data[SettingKey::ACCEPT_ORDERS_AFTER_WORK_TIMES->value])) {
            $data[SettingKey::ACCEPT_ORDERS_AFTER_WORK_TIMES->value] = $data[SettingKey::ACCEPT_ORDERS_AFTER_WORK_TIMES->value] ? 'true' : 'false';
        }

        if (isset($data[SettingKey::ONLINE_PAYMENTS_ENABLED->value])) {
            $data[SettingKey::ONLINE_PAYMENTS_ENABLED->value] = $data[SettingKey::ONLINE_PAYMENTS_ENABLED->value] ? 'true' : 'false';
        }

        if (isset($data[SettingKey::PRODUCT_SHOW_CARDS->value])) {
            $data[SettingKey::PRODUCT_SHOW_CARDS->value] = json_encode($data[SettingKey::PRODUCT_SHOW_CARDS->value]);
        }

        if (isset($data[SettingKey::WORK_TIMES->value])) {
            $data[SettingKey::WORK_TIMES->value] = json_encode($data[SettingKey::WORK_TIMES->value]);
        }

        $settingService->setMultiple($data);

        Notification::make()
            ->success()
            ->title('Settings saved')
            ->body('Application settings have been updated successfully.')
            ->send();
    }
}
