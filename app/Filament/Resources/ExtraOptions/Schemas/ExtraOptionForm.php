<?php

declare(strict_types=1);

namespace App\Filament\Resources\ExtraOptions\Schemas;

use App\Filament\Forms\Components\PosItemSelect;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
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
                                    ->maxLength(255)
                                    ->columnSpan(2),
                                TextInput::make('price')
                                    ->numeric()
                                    ->default(0)
                                    ->prefix('$')
                                    ->required(),
                                Toggle::make('is_default')
                                    ->default(false)
                                    ->label('Default Selection'),
                                TextInput::make('sort_order')
                                    ->numeric()
                                    ->default(0)
                                    ->label('Sort Order'),
                                Radio::make('pos_mapping_type')
                                    ->options([
                                        'pos_item' => 'POS Item',
                                        'notes' => 'Notes',
                                    ])
                                    ->default('pos_item')
                                    ->inline()
                                    ->required()
                                    ->label('POS Mapping Type')
                                    ->live(),
                                Toggle::make('allow_quantity')
                                    ->default(false)
                                    ->label('Allow Quantity Selection')
                                    ->helperText('Allow customers to select quantity for this item'),
                                Repeater::make('posMappings')
                                    ->relationship()
                                    ->schema([
                                        PosItemSelect::make('pos_item_id')
                                            ->columnSpan(2),
                                        Select::make('branch_id')
                                            ->relationship('branch', 'name')
                                            ->label('Branch (Optional)')
                                            ->helperText('Leave empty for all branches'),
                                    ])
                                    ->columns(3)
                                    ->defaultItems(0)
                                    ->addActionLabel('Add POS Mapping')
                                    ->collapsible()
                                    ->visible(fn (callable $get) => $get('pos_mapping_type') === 'pos_item')
                                    ->columnSpanFull(),
                            ])
                            ->columns(3)
                            ->orderColumn('sort_order')
                            ->defaultItems(1)
                            ->collapsible(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
