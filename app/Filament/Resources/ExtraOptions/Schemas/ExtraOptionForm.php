<?php

declare(strict_types=1);

namespace App\Filament\Resources\ExtraOptions\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class ExtraOptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic Information')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('description')
                            ->rows(3)
                            ->maxLength(65535),
                        Toggle::make('is_active')
                            ->default(true)
                            ->required(),
                    ])
                    ->columnSpanFull(),

                Section::make('Option Items')
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('price')
                                    ->numeric()
                                    ->default(0)
                                    ->prefix('$')
                                    ->required(),
                                Toggle::make('is_default')
                                    ->default(false),
                                TextInput::make('sort_order')
                                    ->numeric()
                                    ->default(0),
                            ])
                            ->columns(4)
                            ->orderColumn('sort_order')
                            ->defaultItems(1)
                            ->collapsible(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
