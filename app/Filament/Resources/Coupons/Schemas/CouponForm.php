<?php

declare(strict_types=1);

namespace App\Filament\Resources\Coupons\Schemas;

use App\Models\Area;
use App\Models\Category;
use App\Models\Governorate;
use App\Models\Product;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

final class CouponForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('معلومات أساسية')
                    ->schema([
                        TextInput::make('code')
                            ->label('الرمز')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->columnSpan(1),
                        TextInput::make('name')
                            ->label('الاسم')
                            ->maxLength(255)
                            ->columnSpan(1),
                        Textarea::make('description')
                            ->label('الوصف')
                            ->rows(2)
                            ->maxLength(65535)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('إعدادات الخصم')
                    ->schema([
                        Select::make('type')
                            ->label('النوع')
                            ->required()
                            ->options([
                                'percentage' => 'نسبة مئوية',
                                'fixed' => 'مبلغ ثابت',
                            ])
                            ->default('percentage')
                            ->live()
                            ->columnSpan(1),
                        TextInput::make('value')
                            ->label('القيمة')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->suffix(fn (Get $get) => $get('type') === 'percentage' ? '%' : 'EGP')
                            ->columnSpan(1),
                        DateTimePicker::make('expiry_date')
                            ->label('تاريخ الانتهاء')
                            ->required()
                            ->minDate(now())
                            ->columnSpan(1),
                        Toggle::make('is_active')
                            ->label('نشط')
                            ->default(true)
                            ->columnSpan(1),
                        TextInput::make('max_usage')
                            ->numeric()
                            ->minValue(1)
                            ->label('عدد مرات الاستخدام الأقصى')
                            ->hint('اتركه فارغاً للاستخدام غير المحدود')
                            ->columnSpan(1),
                    ])
                    ->columns(2),

                Section::make('قيود قيمة الطلب')
                    ->schema([
                        TextInput::make('conditions.min_order_total')
                            ->numeric()
                            ->minValue(0)
                            ->label('إجمالي الطلب الأدنى')
                            ->suffix('EGP')
                            ->hint('اتركه فارغاً بدون حد أدنى')
                            ->columnSpan(1),
                        TextInput::make('conditions.max_order_total')
                            ->numeric()
                            ->minValue(0)
                            ->label('إجمالي الطلب الأقصى')
                            ->suffix('EGP')
                            ->hint('اتركه فارغاً بدون حد أقصى')
                            ->columnSpan(1),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('المنتجات والفئات القابلة للتطبيق')
                    ->schema([
                        Select::make('conditions.applicable_to.type')
                            ->label('تطبيق على')
                            ->options([
                                'all' => 'جميع المنتجات',
                                'products' => 'منتجات محددة',
                                'categories' => 'فئات محددة',
                            ])
                            ->default('all')
                            ->live()
                            ->required(),
                        Select::make('conditions.applicable_to.product_ids')
                            ->label('اختر المنتجات')
                            ->multiple()
                            ->searchable()
                            ->options(Product::where('is_active', true)->pluck('name', 'id'))
                            ->visible(fn (Get $get) => $get('conditions.applicable_to.type') === 'products'),
                        Select::make('conditions.applicable_to.category_ids')
                            ->label('اختر الفئات')
                            ->multiple()
                            ->searchable()
                            ->options(Category::where('is_active', true)->pluck('name', 'id'))
                            ->visible(fn (Get $get) => $get('conditions.applicable_to.type') === 'categories'),
                    ])
                    ->collapsible(),

                Section::make('شروط الشحن')
                    ->schema([
                        Toggle::make('conditions.shipping.free_shipping')
                            ->label('تفعيل الشحن المجاني')
                            ->live()
                            ->columnSpan(1),
                        TextInput::make('conditions.shipping.free_shipping_threshold')
                            ->label('حد الشحن المجاني')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('EGP')
                            ->hint('إجمالي الطلب المطلوب للشحن المجاني')
                            ->visible(fn (Get $get) => $get('conditions.shipping.free_shipping'))
                            ->columnSpan(1),
                        Select::make('conditions.shipping.applicable_governorates')
                            ->label('المحافظات القابلة للتطبيق')
                            ->multiple()
                            ->searchable()
                            ->options(Governorate::where('is_active', true)->pluck('name', 'id'))
                            ->hint('اتركه فارغاً لجميع المحافظات')
                            ->columnSpanFull(),
                        Select::make('conditions.shipping.applicable_areas')
                            ->label('المناطق القابلة للتطبيق')
                            ->multiple()
                            ->searchable()
                            ->options(Area::where('is_active', true)->pluck('name', 'id'))
                            ->hint('اتركه فارغاً لجميع المناطق')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('قيود المستخدمين')
                    ->schema([
                        Toggle::make('conditions.usage_restrictions.first_order_only')
                            ->label('للطلب الأول فقط')
                            ->hint('الكوبون صالح فقط للمستخدمين الذين لم يطلبوا من قبل')
                            ->columnSpan(1),
                        Toggle::make('conditions.usage_restrictions.user_specific')
                            ->label('لمستخدمين محددين فقط')
                            ->live()
                            ->columnSpan(1),
                        Select::make('conditions.usage_restrictions.user_ids')
                            ->label('اختر المستخدمين')
                            ->multiple()
                            ->searchable()
                            ->options(User::all()->pluck('name', 'id'))
                            ->visible(fn (Get $get) => $get('conditions.usage_restrictions.user_specific'))
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('قيود الوقت')
                    ->schema([
                        Select::make('conditions.valid_days')
                            ->label('أيام الأسبوع الصالحة')
                            ->multiple()
                            ->options([
                                0 => 'الأحد',
                                1 => 'الاثنين',
                                2 => 'الثلاثاء',
                                3 => 'الأربعاء',
                                4 => 'الخميس',
                                5 => 'الجمعة',
                                6 => 'السبت',
                            ])
                            ->hint('اتركه فارغاً لجميع الأيام')
                            ->columnSpanFull(),
                        TimePicker::make('conditions.valid_hours.start')
                            ->label('صالح من الساعة')
                            ->seconds(false)
                            ->columnSpan(1),
                        TimePicker::make('conditions.valid_hours.end')
                            ->label('صالح حتى الساعة')
                            ->seconds(false)
                            ->columnSpan(1),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('إحصائيات الاستخدام')
                    ->schema([
                        TextInput::make('usage_count')
                            ->label('عدد مرات الاستخدام')
                            ->numeric()
                            ->default(0)
                            ->disabled()
                            ->columnSpan(1),
                        TextInput::make('total_consumed')
                            ->label('إجمالي الخصم الممنوح')
                            ->numeric()
                            ->default(0)
                            ->disabled()
                            ->suffix('EGP')
                            ->columnSpan(1),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}
