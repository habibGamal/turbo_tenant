<?php

declare(strict_types=1);

namespace App\Plugins\StoreSettings\src\Filament\Pages;

use App\Core\Contracts\SettingsRepository;
use App\Core\Plugins\Concerns\BelongsToPlugin;
use App\Core\Plugins\Services\PluginContributions;
use App\Plugins\StoreSettings\src\Contracts\SettingsSection;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * @property-read Schema $form
 */
final class Settings extends Page
{
    use BelongsToPlugin;

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
        $settingService = app(SettingsRepository::class);
        $data = [];

        foreach ($this->settingsSections() as $section) {
            foreach ($section->keys() as $key) {
                $data[$key->value] = $settingService->get($key);
            }
        }

        $this->form->fill($data);
    }

    public function form(Schema $schema): Schema
    {
        $tabs = [];
        foreach ($this->settingsSections() as $section) {
            $tabs[] = $section->tab();
        }

        return $schema
            ->components([
                Form::make([
                    Tabs::make('إعدادات المتجر الشاملة')
                        ->tabs($tabs)
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
        $settingService = app(SettingsRepository::class);

        foreach ($this->settingsSections() as $section) {
            foreach ($section->keys() as $key) {
                if (array_key_exists($key->value, $data)) {
                    $settingService->set($key, $data[$key->value]);
                }
            }
        }

        Notification::make()
            ->success()
            ->title('تم الحفظ بنجاح')
            ->body('تم تحديث إعدادات المتجر بنجاح.')
            ->send();
    }

    /**
     * @return list<SettingsSection>
     */
    private function settingsSections(): array
    {
        /** @var list<SettingsSection> $sections */
        $sections = app(PluginContributions::class)->active('store-settings.sections');

        usort($sections, fn (SettingsSection $a, SettingsSection $b): int => $a->sort() <=> $b->sort());

        return $sections;
    }
}
