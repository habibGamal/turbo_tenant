<?php

declare(strict_types=1);

namespace App\Filament\Resources\WeightOptions\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class WeightOptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Weight Option Details')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->helperText('e.g., "Standard Weight", "Small Portions"'),
                        Select::make('unit')
                            ->options([
                                'kg' => 'Kilogram (kg)',
                                'g' => 'Gram (g)',
                                'lb' => 'Pound (lb)',
                            ])
                            ->default('kg')
                            ->required(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Weight Values')
                    ->schema([
                        Repeater::make('values')
                            ->relationship()
                            ->schema([
                                TextInput::make('value')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0.001)
                                    ->step(0.001)
                                    ->helperText('The weight value (e.g., 0.25, 0.5, 1.0)'),
                                TextInput::make('label')
                                    ->maxLength(255)
                                    ->helperText('Optional display label (e.g., "Quarter kg", "Half kg")'),
                                TextInput::make('sort_order')
                                    ->numeric()
                                    ->default(0)
                                    ->required()
                                    ->helperText('Lower numbers appear first'),
                            ])
                            ->columns(3)
                            ->orderColumn('sort_order')
                            ->defaultItems(1)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? ($state['value'] ?? null) ? "{$state['value']}" : null
                            ),
                    ])
                    ->columnSpanFull()
                    ->description('Define specific weight values that customers can choose from. For example: 0.25 kg, 0.5 kg, 1 kg (without 0.75 kg).'),
            ]);
    }
}
