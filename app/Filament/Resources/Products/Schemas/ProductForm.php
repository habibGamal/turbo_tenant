<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Schemas;

use App\Filament\Forms\Components\PosItemSelect;
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
                Section::make('معلومات أساسية')
                    ->schema([
                        TextInput::make('name')
                            ->label('الاسم')
                            ->required()
                            ->maxLength(255),
                        Select::make('category_id')
                            ->label('الفئة')
                            ->relationship('category', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                        Textarea::make('description')
                            ->label('الوصف')
                            ->rows(3)
                            ->maxLength(65535)
                            ->columnSpanFull(),
                        FileUpload::make('image')
                            ->label('الصورة')
                            ->image()
                            ->imageEditor()
                            ->directory('products')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpan(2),

                Section::make('الحالة')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('نشط')
                            ->default(true),
                        Toggle::make('sell_by_weight')
                            ->label('بيع بالوزن')
                            ->default(false)
                            ->reactive(),
                    ])
                    ->columnSpan(1),

                Section::make('التسعير')
                    ->schema([
                        TextInput::make('base_price')
                            ->label('السعر الأساسي')
                            ->numeric()
                            ->prefix('$')
                            ->maxValue(99999.99),
                        TextInput::make('price_after_discount')
                            ->label('السعر بعد الخصم')
                            ->numeric()
                            ->prefix('$')
                            ->maxValue(99999.99),
                    ])
                    ->columns(2)
                    ->columnSpan(2),

                Section::make('خيارات إضافية')
                    ->schema([
                        Select::make('extra_option_id')
                            ->label('الخيار الإضافي')
                            ->relationship('extraOption', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('weight_options_id')
                            ->label('خيارات الوزن')
                            ->relationship('weightOption', 'name')
                            ->searchable()
                            ->preload()
                            ->visible(fn ($get) => $get('sell_by_weight')),
                    ])
                    ->columns(2)
                    ->columnSpan(2),

                Section::make('ربط POS')
                    ->schema([
                        Repeater::make('posMappings')
                            ->relationship()
                            ->schema([
                                Select::make('branch_id')
                                    ->label('الفرع')
                                    ->relationship('branch', 'name')
                                    ->searchable()
                                    ->preload(),
                                Select::make('variant_id')
                                    ->relationship('variant', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->label('تنويع المنتج (اختياري)'),
                                Select::make('extra_option_item_id')
                                    ->relationship('extraOptionItem', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->label('خيار إضافي (اختياري)'),
                                PosItemSelect::make('pos_item_id'),
                            ])
                            ->columns(4)
                            ->defaultItems(0)
                            ->collapsible()
                            ->addActionLabel('إضافة ربط POS'),
                    ])
                    ->columnSpanFull()
                    ->description('ربط المنتجات مع عناصر نظام POS حسب الفرع والتنويع والخيارات الإضافية'),
            ]);
    }
}
