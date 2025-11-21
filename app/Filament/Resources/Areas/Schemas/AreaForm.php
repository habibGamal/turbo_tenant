<?php

declare(strict_types=1);

namespace App\Filament\Resources\Areas\Schemas;

use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;

final class AreaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                self::getMainSection(),
            ]);
    }

    private static function getMainSection(): Component
    {
        return Section::make('Area Information')
            ->schema([
                Grid::make(2)
                    ->schema([
                        Select::make('governorate_id')
                            ->label('Governorate')
                            ->relationship('governorate', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('branch_id')
                            ->label('Branch')
                            ->relationship('branch', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        TextInput::make('name')
                            ->label('Name (English)')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('name_ar')
                            ->label('Name (Arabic)')
                            ->maxLength(255),

                        TextInput::make('shipping_cost')
                            ->label('Shipping Cost')
                            ->numeric()
                            ->default(0)
                            ->required()
                            ->suffix('EGP'),

                        TextInput::make('sort_order')
                            ->label('Sort Order')
                            ->numeric()
                            ->default(0)
                            ->required(),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->required(),
                    ]),
            ]);
    }
}
