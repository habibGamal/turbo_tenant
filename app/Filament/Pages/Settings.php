<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\SettingKey;
use App\Services\SettingService;
use BackedEnum;
use Filament\Actions\Action;
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
        $this->form->fill($settingService->getAllAsArray());
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
                            TextInput::make(SettingKey::DELIVERY_FEE->value)
                                ->label(SettingKey::DELIVERY_FEE->label())
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

        $settingService->setMultiple($data);

        Notification::make()
            ->success()
            ->title('Settings saved')
            ->body('Application settings have been updated successfully.')
            ->send();
    }
}
