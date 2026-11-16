<?php

declare(strict_types=1);

namespace App\Plugins\LocationsDelivery\src\Filament\Resources\Branches\Schemas;

use App\Core\Plugins\Contracts\FormComponentContributor;
use App\Core\Plugins\Services\PluginContributions;
use App\Rules\PublicHttpUrl;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class BranchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        TextInput::make('name')
                            ->label('الاسم')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('link')
                            ->label('الرابط')
                            ->required()
                            ->rows(3)
                            ->maxLength(65535)
                            ->helperText('أدخل رابط الفرع أو عنوان URL')
                            ->rules([
                                new PublicHttpUrl(),
                            ]),
                        ...self::contributedComponents(),
                        Toggle::make('is_active')
                            ->label('نشط')
                            ->default(true)
                            ->required(),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Fields other plugins add to the branch form ('admin.form.locations-delivery.branch').
     *
     * @return list<mixed>
     */
    private static function contributedComponents(): array
    {
        /** @var PluginContributions $pluginContributions */
        $pluginContributions = app(PluginContributions::class);
        $components = [];

        foreach ($pluginContributions->active('admin.form.locations-delivery.branch') as $contributor) {
            if ($contributor instanceof FormComponentContributor) {
                foreach ($contributor->components() as $component) {
                    $components[] = $component;
                }
            }
        }

        return $components;
    }
}
