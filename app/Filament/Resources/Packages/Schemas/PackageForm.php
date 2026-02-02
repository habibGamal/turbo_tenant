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
                Section::make('معلومات أساسية')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->label('الاسم'),

                        TextInput::make('name_ar')
                            ->maxLength(255)
                            ->label('الاسم (عربي)'),

                        Textarea::make('description')
                            ->required()
                            ->rows(3)
                            ->label('الوصف')
                            ->columnSpanFull(),

                        Textarea::make('description_ar')
                            ->rows(3)
                            ->label('الوصف (عربي)')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpan(2),

                Section::make('الحالة')
                    ->schema([
                        Toggle::make('is_active')
                            ->default(true)
                            ->label('نشط'),

                        Toggle::make('is_featured')
                            ->default(false)
                            ->label('مميز'),
                    ])
                    ->columnSpan(1),

                Section::make('التسعير')
                    ->schema([
                        TextInput::make('price')
                            ->required()
                            ->numeric()
                            ->prefix('$')
                            ->minValue(0)
                            ->label('السعر'),

                        TextInput::make('original_price')
                            ->numeric()
                            ->prefix('$')
                            ->minValue(0)
                            ->label('السعر الأصلي'),

                        TextInput::make('discount_percentage')
                            ->numeric()
                            ->suffix('%')
                            ->minValue(0)
                            ->maxValue(100)
                            ->label('نسبة الخصم'),
                    ])
                    ->columns(3)
                    ->columnSpan(2),

                Section::make('إعدادات العرض')
                    ->schema([
                        TextInput::make('badge')
                            ->maxLength(255)
                            ->label('الشارة'),

                        TextInput::make('badge_ar')
                            ->maxLength(255)
                            ->label('الشارة (عربي)'),

                        TextInput::make('icon')
                            ->default('gift')
                            ->maxLength(255)
                            ->label('الأيقونة'),

                        TextInput::make('gradient')
                            ->default('from-orange-500/10 via-red-500/5 to-pink-500/10')
                            ->maxLength(255)
                            ->label('التدرج اللوني')
                            ->columnSpanFull(),

                        TextInput::make('sort_order')
                            ->numeric()
                            ->default(0)
                            ->label('ترتيب العرض'),
                    ])
                    ->columns(3)
                    ->columnSpan(2),

                Section::make('فترة الصلاحية')
                    ->schema([
                        DateTimePicker::make('valid_from')
                            ->label('صالح من'),

                        DateTimePicker::make('valid_until')
                            ->label('صالح حتى'),
                    ])
                    ->columns(2)
                    ->columnSpan(2)
                    ->collapsible(),

                Section::make('محتويات الباقة')
                    ->schema([
                        Repeater::make('groups')
                            ->relationship()
                            ->schema([
                                TextInput::make('name')
                                    ->label('اسم المجموعة')
                                    ->placeholder('مثل: اختر طبقك الرئيسي'),

                                TextInput::make('name_ar')
                                    ->label('اسم المجموعة (عربي)'),

                                Select::make('selection_type')
                                    ->required()
                                    ->options([
                                        'all' => 'جميع العناصر (ثابت)',
                                        'choose_one' => 'اختر واحد',
                                        'choose_multiple' => 'اختر متعدد',
                                    ])
                                    ->default('all')
                                    ->reactive()
                                    ->label('نوع الاختيار'),

                                TextInput::make('min_selections')
                                    ->numeric()
                                    ->minValue(0)
                                    ->label('الحد الأدنى للاختيارات')
                                    ->visible(fn ($get) => in_array($get('selection_type'), ['choose_one', 'choose_multiple'])),

                                TextInput::make('max_selections')
                                    ->numeric()
                                    ->minValue(0)
                                    ->label('الحد الأقصى للاختيارات')
                                    ->visible(fn ($get) => in_array($get('selection_type'), ['choose_one', 'choose_multiple'])),

                                TextInput::make('sort_order')
                                    ->numeric()
                                    ->default(0)
                                    ->label('ترتيب العرض'),

                                Repeater::make('items')
                                    ->relationship()
                                    ->schema([
                                        Select::make('product_id')
                                            ->required()
                                            ->label('المنتج')
                                            ->options(Product::where('is_active', true)->pluck('name', 'id'))
                                            ->searchable()
                                            ->reactive()
                                            ->preload(),

                                        Select::make('variant_id')
                                            ->label('تنويع (اختياري)')
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
                                            ->label('الكمية'),

                                        TextInput::make('price_adjustment')
                                            ->numeric()
                                            ->default(0)
                                            ->prefix('$')
                                            ->label('تعديل السعر')
                                            ->helperText('رسوم إضافية لهذا الخيار'),

                                        Toggle::make('is_default')
                                            ->label('الاختيار الافتراضي')
                                            ->helperText('محدد مسبقاً للمجموعات الشرطية'),

                                        TextInput::make('sort_order')
                                            ->numeric()
                                            ->default(0)
                                            ->label('ترتيب العرض'),
                                    ])
                                    ->columns(3)
                                    ->defaultItems(1)
                                    ->addActionLabel('إضافة عنصر')
                                    ->collapsible()
                                    ->itemLabel(fn (array $state): ?string => Product::find($state['product_id'])?->name ?? 'عنصر')
                                    ->columnSpanFull(),
                            ])
                            ->columns(3)
                            ->defaultItems(1)
                            ->addActionLabel('إضافة مجموعة')
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? 'مجموعة')
                            ->reorderable()
                            ->cloneable(),
                    ])
                    ->columnSpanFull()
                    ->description('حدد مجموعات وعناصر الباقة. المجموعات يمكن أن تكون ثابتة (جميع العناصر) أو شرطية (اختر واحداً/متعدد).'),
            ]);
    }
}
