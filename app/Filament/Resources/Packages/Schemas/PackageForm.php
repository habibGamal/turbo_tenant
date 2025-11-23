<?php

declare(strict_types=1);

namespace App\Filament\Resources\Packages\Schemas;

use App\Models\Product;
use App\Models\ProductVariant;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class PackageForm
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
                            ->maxLength(255)
                            ->label('Name'),

                        TextInput::make('name_ar')
                            ->maxLength(255)
                            ->label('Name (Arabic)'),

                        Textarea::make('description')
                            ->required()
                            ->rows(3)
                            ->label('Description')
                            ->columnSpanFull(),

                        Textarea::make('description_ar')
                            ->rows(3)
                            ->label('Description (Arabic)')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpan(2),

                Section::make('Status')
                    ->schema([
                        Toggle::make('is_active')
                            ->default(true)
                            ->label('Active'),

                        Toggle::make('is_featured')
                            ->default(false)
                            ->label('Featured'),
                    ])
                    ->columnSpan(1),

                Section::make('Pricing')
                    ->schema([
                        TextInput::make('price')
                            ->required()
                            ->numeric()
                            ->prefix('$')
                            ->minValue(0)
                            ->label('Price'),

                        TextInput::make('original_price')
                            ->numeric()
                            ->prefix('$')
                            ->minValue(0)
                            ->label('Original Price'),

                        TextInput::make('discount_percentage')
                            ->numeric()
                            ->suffix('%')
                            ->minValue(0)
                            ->maxValue(100)
                            ->label('Discount %'),
                    ])
                    ->columns(3)
                    ->columnSpan(2),

                Section::make('Display Settings')
                    ->schema([
                        TextInput::make('badge')
                            ->maxLength(255)
                            ->label('Badge'),

                        TextInput::make('badge_ar')
                            ->maxLength(255)
                            ->label('Badge (Arabic)'),

                        TextInput::make('icon')
                            ->default('gift')
                            ->maxLength(255)
                            ->label('Icon'),

                        TextInput::make('gradient')
                            ->default('from-orange-500/10 via-red-500/5 to-pink-500/10')
                            ->maxLength(255)
                            ->label('Gradient')
                            ->columnSpanFull(),

                        TextInput::make('sort_order')
                            ->numeric()
                            ->default(0)
                            ->label('Sort Order'),
                    ])
                    ->columns(3)
                    ->columnSpan(2),

                Section::make('Validity Period')
                    ->schema([
                        DateTimePicker::make('valid_from')
                            ->label('Valid From'),

                        DateTimePicker::make('valid_until')
                            ->label('Valid Until'),
                    ])
                    ->columns(2)
                    ->columnSpan(2)
                    ->collapsible(),

                Section::make('Package Contents')
                    ->schema([
                        Repeater::make('groups')
                            ->relationship()
                            ->schema([
                                TextInput::make('name')
                                    ->label('Group Name')
                                    ->placeholder('e.g., Choose Your Main Dish'),

                                TextInput::make('name_ar')
                                    ->label('Group Name (Arabic)'),

                                Select::make('selection_type')
                                    ->required()
                                    ->options([
                                        'all' => 'All Items (Fixed)',
                                        'choose_one' => 'Choose One',
                                        'choose_multiple' => 'Choose Multiple',
                                    ])
                                    ->default('all')
                                    ->reactive()
                                    ->label('Selection Type'),

                                TextInput::make('min_selections')
                                    ->numeric()
                                    ->minValue(0)
                                    ->label('Min Selections')
                                    ->visible(fn ($get) => in_array($get('selection_type'), ['choose_one', 'choose_multiple'])),

                                TextInput::make('max_selections')
                                    ->numeric()
                                    ->minValue(0)
                                    ->label('Max Selections')
                                    ->visible(fn ($get) => in_array($get('selection_type'), ['choose_one', 'choose_multiple'])),

                                TextInput::make('sort_order')
                                    ->numeric()
                                    ->default(0)
                                    ->label('Sort Order'),

                                Repeater::make('items')
                                    ->relationship()
                                    ->schema([
                                        Select::make('product_id')
                                            ->required()
                                            ->label('Product')
                                            ->options(Product::where('is_active', true)->pluck('name', 'id'))
                                            ->searchable()
                                            ->reactive()
                                            ->preload(),

                                        Select::make('variant_id')
                                            ->label('Variant (Optional)')
                                            ->options(function ($get) {
                                                $productId = $get('product_id');
                                                if (! $productId) {
                                                    return [];
                                                }

                                                return ProductVariant::where('product_id', $productId)
                                                    ->where('is_available', true)
                                                    ->pluck('name', 'id');
                                            })
                                            ->searchable()
                                            ->preload(),

                                        TextInput::make('quantity')
                                            ->required()
                                            ->numeric()
                                            ->default(1)
                                            ->minValue(1)
                                            ->label('Quantity'),

                                        TextInput::make('price_adjustment')
                                            ->numeric()
                                            ->default(0)
                                            ->prefix('$')
                                            ->label('Price Adjustment')
                                            ->helperText('Extra charge for this option'),

                                        Toggle::make('is_default')
                                            ->label('Default Selection')
                                            ->helperText('Pre-selected for conditional groups'),

                                        TextInput::make('sort_order')
                                            ->numeric()
                                            ->default(0)
                                            ->label('Sort Order'),
                                    ])
                                    ->columns(3)
                                    ->defaultItems(1)
                                    ->addActionLabel('Add Item')
                                    ->collapsible()
                                    ->itemLabel(fn (array $state): ?string => Product::find($state['product_id'])?->name ?? 'Item')
                                    ->columnSpanFull(),
                            ])
                            ->columns(3)
                            ->defaultItems(1)
                            ->addActionLabel('Add Group')
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? 'Group')
                            ->reorderable()
                            ->cloneable(),
                    ])
                    ->columnSpanFull()
                    ->description('Define package groups and items. Groups can be fixed (all items) or conditional (choose one/multiple).'),
            ]);
    }
}
