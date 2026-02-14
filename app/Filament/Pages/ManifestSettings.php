<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Services\ManifestService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * @property-read Schema $form
 */
final class ManifestSettings extends Page
{
    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDevicePhoneMobile;

    protected static ?string $navigationLabel = 'PWA Manifest';

    protected static ?string $title = 'PWA Manifest Settings';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected string $view = 'filament.pages.settings';

    protected static ?int $navigationSort = 101;

    public function mount(): void
    {
        $manifestService = app(ManifestService::class);
        $this->form->fill($manifestService->getManifest());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    Section::make('App Identity')
                        ->schema([
                            TextInput::make('name')
                                ->label('Full Name')
                                ->required()
                                ->maxLength(255)
                                ->helperText('The full name of your app as it appears on the home screen'),
                            TextInput::make('short_name')
                                ->label('Short Name')
                                ->required()
                                ->maxLength(12)
                                ->helperText('Short name displayed under the icon (max 12 characters recommended)'),
                            TextInput::make('id')
                                ->label('App ID')
                                ->required()
                                ->maxLength(255)
                                ->helperText('Unique identifier for your app (e.g., com.example.app)')
                                ->placeholder('com.example.app'),
                        ])
                        ->columns(2),

                    Section::make('Appearance')
                        ->schema([
                            ColorPicker::make('theme_color')
                                ->label('Theme Color')
                                ->required()
                                ->helperText('Color of the browser UI and address bar'),
                            ColorPicker::make('background_color')
                                ->label('Background Color')
                                ->required()
                                ->helperText('Background color shown while the app loads'),
                        ])
                        ->columns(2),

                    Section::make('Display Settings')
                        ->schema([
                            TextInput::make('start_url')
                                ->label('Start URL')
                                ->required()
                                ->maxLength(255)
                                ->default('.')
                                ->helperText('The URL that loads when the app launches'),
                            Select::make('display')
                                ->label('Display Mode')
                                ->required()
                                ->options([
                                    'fullscreen' => 'Fullscreen',
                                    'standalone' => 'Standalone',
                                    'minimal-ui' => 'Minimal UI',
                                    'browser' => 'Browser',
                                ])
                                ->default('standalone')
                                ->helperText('How the app should be displayed'),
                            Select::make('orientation')
                                ->label('Orientation')
                                ->required()
                                ->options([
                                    'any' => 'Any',
                                    'natural' => 'Natural',
                                    'landscape' => 'Landscape',
                                    'portrait' => 'Portrait',
                                ])
                                ->default('natural')
                                ->helperText('Default orientation for the app'),
                        ])
                        ->columns(3),

                    Section::make('App Icons')
                        ->schema([
                            Repeater::make('icons')
                                ->schema([
                                    FileUpload::make('src')
                                        ->label('Icon Source')
                                        ->image()
                                        ->directory('pwa-icons')
                                        ->required(),
                                    TextInput::make('sizes')
                                        ->label('Sizes')
                                        ->required()
                                        ->maxLength(50)
                                        ->placeholder('192x192')
                                        ->helperText('Icon dimensions (e.g., 192x192)'),
                                    TextInput::make('type')
                                        ->label('MIME Type')
                                        ->required()
                                        ->maxLength(50)
                                        ->placeholder('image/png')
                                        ->helperText('Image MIME type'),
                                ])
                                ->columns(3)
                                ->defaultItems(3)
                                ->addActionLabel('Add Icon')
                                ->helperText('Define icons for different screen sizes and platforms'),
                        ]),
                ])
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('Save Manifest')
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

        $manifestService = app(ManifestService::class);
        $manifestService->saveManifest($data);

        Notification::make()
            ->success()
            ->title('Manifest saved')
            ->body('PWA manifest has been updated successfully.')
            ->send();
    }
}
