<?php

declare(strict_types=1);

namespace App\Filament\Resources\Sections\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

final class SectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('معلومات أساسية')
                    ->schema([
                        TextInput::make('title')
                            ->label('العنوان')
                            ->required()
                            ->maxLength(255),
                        Select::make('location')
                            ->label('الموقع')
                            ->options([
                                'home' => 'الرئيسية',
                                'menu' => 'القائمة',
                                'featured' => 'مميز',
                            ])
                            ->required(),
                        TextInput::make('sort_order')
                            ->label('ترتيب العرض')
                            ->numeric()
                            ->default(0)
                            ->required(),
                        Toggle::make('is_active')
                            ->label('نشط')
                            ->default(true)
                            ->required(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('المنتجات')
                    ->schema([
                        Select::make('category_id')
                            ->label('إضافة منتجات من فئة')
                            ->options(\App\Models\Category::pluck('name', 'id'))
                            ->searchable()
                            ->live()
                            ->dehydrated(false)
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                if (! $state) {
                                    return;
                                }

                                $categoryProducts = \App\Models\Product::where('category_id', $state)->pluck('id')->toArray();
                                $existingProducts = $get('products') ?? [];

                                $set('products', array_unique(array_merge($existingProducts, $categoryProducts)));
                                $set('category_id', null);
                            }),

                        Select::make('products')
                            ->label('المنتجات')
                            ->relationship('products', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
