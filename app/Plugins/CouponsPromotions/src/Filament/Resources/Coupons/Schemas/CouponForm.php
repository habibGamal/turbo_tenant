<?php

declare(strict_types=1);

namespace App\Plugins\CouponsPromotions\src\Filament\Resources\Coupons\Schemas;

use App\Models\User;
use App\Plugins\CatalogProducts\src\Models\Category;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\LocationsDelivery\src\Models\Area;
use App\Plugins\LocationsDelivery\src\Models\Governorate;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

final class CouponForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('بيانات الكوبون')
                    ->tabs([
                        Tab::make('الخصم والرمز')
                            ->icon('heroicon-o-ticket')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('code')
                                        ->label('رمز الكوبون (البروموكود)')
                                        ->required()
                                        ->unique(ignoreRecord: true)
                                        ->maxLength(255),
                                    TextInput::make('name')
                                        ->label('اسم الحملة الترويجية')
                                        ->maxLength(255),
                                    Select::make('type')
                                        ->label('نوع الخصم')
                                        ->required()
                                        ->options([
                                            'percentage' => 'نسبة مئوية (%)',
                                            'fixed' => 'مبلغ ثابت',
                                        ])
                                        ->default('percentage')
                                        ->live(),
                                    TextInput::make('value')
                                        ->label('قيمة الخصم')
                                        ->required()
                                        ->numeric()
                                        ->minValue(0)
                                        ->suffix(fn (Get $get) => $get('type') === 'percentage' ? '%' : 'EGP'),
                                    DateTimePicker::make('expiry_date')
                                        ->label('تاريخ ووقت الانتهاء')
                                        ->required()
                                        ->minDate(now()),
                                    TextInput::make('max_usage')
                                        ->label('الحد الأقصى لمرات الاستخدام')
                                        ->numeric()
                                        ->minValue(1)
                                        ->helperText('اتركه فارغاً للاستخدام غير المحدود'),
                                    Toggle::make('is_active')
                                        ->label('كوبون نشط')
                                        ->default(true),
                                ]),
                                Textarea::make('description')
                                    ->label('وصف الكوبون وشروطه للعملاء')
                                    ->rows(2)
                                    ->maxLength(65535)
                                    ->columnSpanFull(),
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
                            ]),

                        Tab::make('شروط الطلب والمنتجات')
                            ->icon('heroicon-o-shopping-bag')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('conditions.min_order_total')
                                        ->label('الحد الأدنى لقيمة الطلب')
                                        ->numeric()
                                        ->minValue(0)
                                        ->suffix('EGP')
                                        ->helperText('أقل مبلغ سلة مطلوب لتفعيل الكوبون'),
                                    TextInput::make('conditions.max_order_total')
                                        ->label('الحد الأقصى لقيمة الطلب')
                                        ->numeric()
                                        ->minValue(0)
                                        ->suffix('EGP'),
                                ]),
                                Select::make('conditions.applicable_to.type')
                                    ->label('نطاق تطبيق الكوبون')
                                    ->options([
                                        'all' => 'جميع منتجات المتجر',
                                        'products' => 'منتجات محددة فقط',
                                        'categories' => 'فئات معينة فقط',
                                    ])
                                    ->default('all')
                                    ->live()
                                    ->required(),
                                Select::make('conditions.applicable_to.product_ids')
                                    ->label('اختر المنتجات المشمولة')
                                    ->multiple()
                                    ->searchable()
                                    ->options(Product::where('is_active', true)->pluck('name', 'id'))
                                    ->visible(fn (Get $get) => $get('conditions.applicable_to.type') === 'products'),
                                Select::make('conditions.applicable_to.category_ids')
                                    ->label('اختر الفئات المشمولة')
                                    ->multiple()
                                    ->searchable()
                                    ->options(Category::where('is_active', true)->pluck('name', 'id'))
                                    ->visible(fn (Get $get) => $get('conditions.applicable_to.type') === 'categories'),
                            ]),

                        Tab::make('القيود والشحن والتوقيت')
                            ->icon('heroicon-o-clock')
                            ->schema([
                                Section::make('قيود الشحن')->schema([
                                    Toggle::make('conditions.shipping.free_shipping')
                                        ->label('يمنح شحن مجاني')
                                        ->live(),
                                    TextInput::make('conditions.shipping.free_shipping_threshold')
                                        ->label('الحد الأدنى للشحن المجاني')
                                        ->numeric()
                                        ->minValue(0)
                                        ->suffix('EGP')
                                        ->visible(fn (Get $get) => (bool) $get('conditions.shipping.free_shipping')),
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
                                ])->collapsible(),

                                Section::make('قيود الاستخدام للعملاء')->schema([
                                    Toggle::make('conditions.usage_restrictions.first_order_only')
                                        ->label('صالح للطلب الأول للعميل فقط'),
                                    Toggle::make('conditions.usage_restrictions.user_specific')
                                        ->label('لمستخدمين محددين فقط')
                                        ->live(),
                                    Select::make('conditions.usage_restrictions.user_ids')
                                        ->label('اختر المستخدمين')
                                        ->multiple()
                                        ->searchable()
                                        ->options(User::all()->pluck('name', 'id'))
                                        ->visible(fn (Get $get) => (bool) $get('conditions.usage_restrictions.user_specific'))
                                        ->columnSpanFull(),
                                ])->collapsible(),

                                Section::make('قيود الوقت')->schema([
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
                                    Grid::make(2)->schema([
                                        TimePicker::make('conditions.valid_hours.start')
                                            ->label('صالح من الساعة')
                                            ->seconds(false),
                                        TimePicker::make('conditions.valid_hours.end')
                                            ->label('صالح حتى الساعة')
                                            ->seconds(false),
                                    ]),
                                ])->collapsible(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
