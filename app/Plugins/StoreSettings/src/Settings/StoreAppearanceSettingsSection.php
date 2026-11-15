<?php

declare(strict_types=1);

namespace App\Plugins\StoreSettings\src\Settings;

use App\Core\Contracts\SettingDefinition;
use App\Enums\SettingKey;
use App\Plugins\StoreSettings\src\Contracts\SettingsSection;
use App\Plugins\StoreSettings\src\Theme\ThemePresets;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Set;

final class StoreAppearanceSettingsSection implements SettingsSection
{
    public function pluginId(): string
    {
        return 'store-settings';
    }

    public function sort(): int
    {
        return 15;
    }

    public function tab(): Tab
    {
        return Tab::make('المظهر والألوان')
            ->icon('heroicon-o-swatch')
            ->schema([
                Section::make('السمة الجاهزة')
                    ->schema([
                        Select::make('theme.preset')
                            ->label('اختر سمة جاهزة')
                            ->options(ThemePresets::options())
                            ->placeholder('اختر سمة لتعبئة الألوان تلقائياً')
                            ->default('default')
                            ->live()
                            ->afterStateUpdated(function (?string $state, Set $set): void {
                                if (! $state) {
                                    return;
                                }

                                $preset = ThemePresets::get($state);
                                if ($preset !== null) {
                                    $set('theme.primary', $preset['primary']);
                                    $set('theme.secondary', $preset['secondary']);
                                    $set('theme.radius', $preset['radius']);
                                }
                            })
                            ->helperText('يمكنك اختيار سمة جاهزة لتعبئة الألوان ودرجة الحواف تلقائياً أو تعديلها يدوياً أدناه.'),
                    ]),

                Section::make('تخصيص الألوان والحواف')
                    ->schema([
                        Grid::make(3)->schema([
                            ColorPicker::make('theme.primary')
                                ->label('اللون الأساسي (Primary)')
                                ->nullable()
                                ->regex('/^#[0-9a-fA-F]{6}$/')
                                ->helperText('اللون الرئيسي للأزرار والعناصر التفاعلية البارزة.'),
                            ColorPicker::make('theme.secondary')
                                ->label('اللون الثانوي (Secondary)')
                                ->nullable()
                                ->regex('/^#[0-9a-fA-F]{6}$/')
                                ->helperText('اللون الثانوي للخلفيات الهادئة والبطاقات المميزة.'),
                            Select::make('theme.radius')
                                ->label('درجة انحناء الحواف (Border Radius)')
                                ->options(ThemePresets::radiusOptions())
                                ->nullable()
                                ->helperText('درجة استدارة حواف الأزرار والبطاقات وحقول الإدخال.'),
                        ]),
                    ]),
            ]);
    }

    /**
     * @return list<SettingDefinition>
     */
    public function keys(): array
    {
        return [SettingKey::THEME];
    }
}
