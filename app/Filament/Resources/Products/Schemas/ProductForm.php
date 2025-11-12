<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Basic Information')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Select::make('category_id')
                            ->relationship('category', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                        Textarea::make('description')
                            ->rows(3)
                            ->maxLength(65535)
                            ->columnSpanFull(),
                        FileUpload::make('image')
                            ->image()
                            ->imageEditor()
                            ->directory('products')
                            ->required()
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpan(2),

                Section::make('Status')
                    ->schema([
                        Toggle::make('is_active')
                            ->default(true),
                        Toggle::make('sell_by_weight')
                            ->default(false)
                            ->reactive(),
                    ])
                    ->columnSpan(1),

                Section::make('Pricing')
                    ->schema([
                        TextInput::make('base_price')
                            ->numeric()
                            ->prefix('$')
                            ->maxValue(99999.99),
                        TextInput::make('price_after_discount')
                            ->numeric()
                            ->prefix('$')
                            ->maxValue(99999.99),
                    ])
                    ->columns(2)
                    ->columnSpan(2),

                Section::make('Additional Options')
                    ->schema([
                        Select::make('extra_option_id')
                            ->relationship('extraOption', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('weight_options_id')
                            ->relationship('weightOption', 'name')
                            ->searchable()
                            ->preload()
                            ->visible(fn ($get) => $get('sell_by_weight')),
                        TextInput::make('single_pos_ref')
                            ->label('POS Reference')
                            ->maxLength(255),
                    ])
                    ->columns(2)
                    ->columnSpan(2),

                Section::make('Product Variants')
                    ->schema([
                        Repeater::make('variants')
                            ->relationship()
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('price')
                                    ->numeric()
                                    ->required()
                                    ->prefix('$'),
                                Toggle::make('is_available')
                                    ->default(true),
                                TextInput::make('sort_order')
                                    ->numeric()
                                    ->default(0),
                            ])
                            ->columns(4)
                            ->orderColumn('sort_order')
                            ->defaultItems(0)
                            ->collapsible(),
                    ])
                    ->columnSpanFull(),

                Section::make('POS Mappings')
                    ->schema([
                        Repeater::make('posMappings')
                            ->relationship()
                            ->schema([
                                Select::make('branch_id')
                                    ->relationship('branch', 'name')
                                    ->searchable()
                                    ->preload(),
                                Select::make('variant_id')
                                    ->relationship('variant', 'name')
                                    ->searchable()
                                    ->preload(),
                                TextInput::make('pos_item_id')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('pos_category')
                                    ->maxLength(255),
                            ])
                            ->columns(4)
                            ->defaultItems(0)
                            ->collapsible(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
