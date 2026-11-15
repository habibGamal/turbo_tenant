<?php

declare(strict_types=1);

namespace App\Plugins\CatalogProducts\src\Filament\Resources\ExtraOptions\Schemas;

use App\Core\Contracts\SettingsRepository;
use App\Core\Plugins\Services\PluginContributions;
use App\Plugins\CatalogProducts\src\Contracts\ExtraOptionFormExtender;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class ExtraOptionForm
{
    public static function configure(Schema $schema): Schema
    {
        $itemComponents = [
            TextInput::make('name')
                ->label('الاسم')
                ->required()
                ->maxLength(255)
                ->columnSpan(2),
            TextInput::make('name_ar')
                ->label('الاسم بالعربية')
                ->maxLength(255)
                ->columnSpan(2),
            TextInput::make('price')
                ->label('السعر')
                ->numeric()
                ->default(0)
                ->prefix(fn () => app()->bound(SettingsRepository::class) ? app(SettingsRepository::class)->get('currency', 'EGP') : 'EGP')
                ->required(),
            Toggle::make('is_default')
                ->default(false)
                ->label('الاختيار الافتراضي'),
            TextInput::make('sort_order')
                ->numeric()
                ->default(0)
                ->label('ترتيب العرض'),
            Toggle::make('allow_quantity')
                ->default(false)
                ->label('السماح باختيار الكمية')
                ->helperText('السماح للعملاء باختيار كمية لهذا العنصر'),
        ];

        /** @var PluginContributions $pluginContributions */
        $pluginContributions = app(PluginContributions::class);
        foreach ($pluginContributions->active('catalog.extra-option.form') as $extender) {
            if ($extender instanceof ExtraOptionFormExtender) {
                foreach ($extender->itemComponents() as $component) {
                    $itemComponents[] = $component;
                }
            }
        }

        return $schema
            ->components([
                Section::make('معلومات أساسية')
                    ->schema([
                        TextInput::make('name')
                            ->label('الاسم')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('name_ar')
                            ->label('الاسم بالعربية')
                            ->maxLength(255),
                        Textarea::make('description')
                            ->label('الوصف')
                            ->rows(3)
                            ->maxLength(65535),
                        Toggle::make('is_active')
                            ->label('نشط')
                            ->default(true)
                            ->required(),
                    ])
                    ->columnSpanFull(),

                Section::make('قواعد الاختيار الافتراضية')
                    ->description('القواعد الافتراضية التي تنطبق على هذه المجموعة عند ربطها بالمنتجات')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('min_selections')
                                ->label('الحد الأدنى للاختيارات الافتراضي')
                                ->numeric()
                                ->default(0)
                                ->minValue(0)
                                ->helperText('0 = اختياري بالكامل'),
                            TextInput::make('max_selections')
                                ->label('الحد الأقصى للاختيارات الافتراضي')
                                ->numeric()
                                ->minValue(1)
                                ->helperText('اتركه فارغاً للاختيار غير المحدود'),
                            Toggle::make('allow_multiple')
                                ->label('السماح باختيار أكثر من خيار')
                                ->default(true),
                        ]),
                    ])
                    ->columnSpanFull(),

                Section::make('عناصر الخيار')
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->schema($itemComponents)
                            ->columns(3)
                            ->orderColumn('sort_order')
                            ->defaultItems(1)
                            ->collapsible(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
