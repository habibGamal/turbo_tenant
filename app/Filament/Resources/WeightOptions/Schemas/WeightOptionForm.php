<?php

declare(strict_types=1);

namespace App\Filament\Resources\WeightOptions\Schemas;

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
                Section::make()
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('min_weight')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->step(0.001),
                        TextInput::make('max_weight')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->step(0.001),
                        TextInput::make('step')
                            ->required()
                            ->numeric()
                            ->default(0.5)
                            ->minValue(0)
                            ->step(0.001),
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
            ]);
    }
}
