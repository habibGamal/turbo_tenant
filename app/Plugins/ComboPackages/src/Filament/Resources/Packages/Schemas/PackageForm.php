<?php

declare(strict_types=1);

namespace App\Plugins\ComboPackages\src\Filament\Resources\Packages\Schemas;

use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Models\ProductVariant;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

final class PackageForm
{
    public static function configure(Schema $schema): Schema
    {
        $productClass = class_exists(Product::class)
            ? Product::class
            : Product::class;

        $variantClass = class_exists(ProductVariant::class)
            ? ProductVariant::class
            : ProductVariant::class;

        return $schema
            ->components([
                Tabs::make('بيانات الباقة')
                    ->tabs([
                        Tab::make('البيانات والتسعير')
                            ->icon('heroicon-o-cube')
                            ->schema([
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
                                    ->columns(2),

                                Section::make('التسعير والصلاحية')
                                    ->schema([
                                        TextInput::make('price')
                                            ->required()
                                            ->numeric()
                                            ->prefix('EGP')
                                            ->minValue(0)
                                            ->label('السعر'),

                                        TextInput::make('original_price')
                                            ->numeric()
                                            ->prefix('EGP')
                                            ->minValue(0)
                                            ->label('السعر الأصلي'),

                                        TextInput::make('discount_percentage')
                                            ->numeric()
                                            ->suffix('%')
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->label('نسبة الخصم'),

                                        DateTimePicker::make('valid_from')
                                            ->label('صالح من'),

                                        DateTimePicker::make('valid_until')
                                            ->label('صالح حتى'),
                                    ])
                                    ->columns(3),

                                Section::make('حالة الباقة')
                                    ->schema([
                                        Toggle::make('is_active')
                                            ->default(true)
                                            ->label('نشط'),

                                        Toggle::make('is_featured')
                                            ->default(false)
                                            ->label('مميز'),
                                    ])
                                    ->columns(2),
                            ]),

                        Tab::make('المظهر والتصميم')
                            ->icon('heroicon-o-paint-brush')
                            ->schema([
                                Section::make('إعدادات العرض والشارات')
                                    ->schema([
                                        TextInput::make('badge')
                                            ->maxLength(255)
                                            ->label('الشارة'),

                                        TextInput::make('badge_ar')
                                            ->maxLength(255)
                                            ->label('الشارة (عربي)'),

                                        Select::make('icon')
                                            ->label('أيقونة الباقة')
                                            ->options([
                                                'gift' => 'هدية (Gift)',
                                                'fire' => 'لهب / مميز (Fire)',
                                                'sparkles' => 'بريق / حصري (Sparkles)',
                                                'bolt' => 'برق / وجبة سريعة (Bolt)',
                                                'heart' => 'مفضل (Heart)',
                                                'tag' => 'توفير / خصم (Tag)',
                                            ])
                                            ->default('gift')
                                            ->required(),

                                        Select::make('gradient')
                                            ->label('التدرج اللوني لبطاقة الباقة')
                                            ->options([
                                                'from-orange-500/20 via-red-500/10 to-pink-500/20' => 'برتقالي وأحمر دافئ (Sunset)',
                                                'from-emerald-500/20 via-teal-500/10 to-cyan-500/20' => 'أخضر وزمردي منعش (Fresh)',
                                                'from-blue-500/20 via-indigo-500/10 to-purple-500/20' => 'أزرق وبنفسجي ملكي (Royal)',
                                                'from-amber-500/20 via-yellow-500/10 to-orange-500/20' => 'ذهبي ومميز (Gold)',
                                                'from-zinc-500/20 via-neutral-500/10 to-stone-500/20' => 'رمادي كلاسيكي (Neutral)',
                                            ])
                                            ->default('from-orange-500/20 via-red-500/10 to-pink-500/20')
                                            ->required(),

                                        TextInput::make('sort_order')
                                            ->numeric()
                                            ->default(0)
                                            ->label('ترتيب العرض'),
                                    ])
                                    ->columns(2),
                            ]),

                        Tab::make('محتويات ومجموعات الباقة')
                            ->icon('heroicon-o-rectangle-stack')
                            ->schema([
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
                                                    ->visible(fn ($get) => in_array($get('selection_type'), ['choose_one', 'choose_multiple'], true)),

                                                TextInput::make('max_selections')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->label('الحد الأقصى للاختيارات')
                                                    ->visible(fn ($get) => in_array($get('selection_type'), ['choose_one', 'choose_multiple'], true)),

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
                                                            ->options($productClass::where('is_active', true)->pluck('name', 'id'))
                                                            ->searchable()
                                                            ->reactive()
                                                            ->preload(),

                                                        Select::make('variant_id')
                                                            ->label('تنويع (اختياري)')
                                                            ->options(function ($get) use ($variantClass) {
                                                                $productId = $get('product_id');
                                                                if (! $productId) {
                                                                    return [];
                                                                }

                                                                return $variantClass::where('product_id', $productId)
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
                                                            ->prefix('EGP')
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
                                                    ->itemLabel(fn (array $state): ?string => $productClass::find($state['product_id'])?->name ?? 'عنصر')
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
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
