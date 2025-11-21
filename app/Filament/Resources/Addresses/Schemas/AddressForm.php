<?php

declare(strict_types=1);

namespace App\Filament\Resources\Addresses\Schemas;

use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;

final class AddressForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                self::getMainSection(),
                self::getAddressDetailsSection(),
            ]);
    }

    private static function getMainSection(): Component
    {
        return Section::make('User & Area')
            ->schema([
                Grid::make(2)
                    ->schema([
                        Select::make('user_id')
                            ->label('User')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('area_id')
                            ->label('Area')
                            ->relationship('area', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        TextInput::make('phone_number')
                            ->label('Phone Number')
                            ->tel()
                            ->required()
                            ->maxLength(255),

                        Toggle::make('is_default')
                            ->label('Default Address')
                            ->default(false),
                    ]),
            ]);
    }

    private static function getAddressDetailsSection(): Component
    {
        return Section::make('Address Details')
            ->schema([
                Grid::make(2)
                    ->schema([
                        TextInput::make('street')
                            ->label('Street')
                            ->maxLength(255),

                        TextInput::make('building')
                            ->label('Building')
                            ->maxLength(255),

                        TextInput::make('floor')
                            ->label('Floor')
                            ->maxLength(255),

                        TextInput::make('apartment')
                            ->label('Apartment')
                            ->maxLength(255),
                    ]),

                Textarea::make('full_address')
                    ->label('Full Address')
                    ->rows(3)
                    ->columnSpanFull(),

                Textarea::make('notes')
                    ->label('Notes')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }
}
