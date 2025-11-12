<?php

declare(strict_types=1);

namespace App\Filament\Resources\Sections\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class SectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic Information')
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        Select::make('location')
                            ->options([
                                'home' => 'Home',
                                'menu' => 'Menu',
                                'featured' => 'Featured',
                            ])
                            ->required(),
                        TextInput::make('sort_order')
                            ->numeric()
                            ->default(0)
                            ->required(),
                        Toggle::make('is_active')
                            ->default(true)
                            ->required(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Products')
                    ->schema([
                        Select::make('products')
                            ->relationship('products', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
