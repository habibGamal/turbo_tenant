<?php

declare(strict_types=1);

namespace App\Plugins\CatalogProducts\src\Filament\Resources\Products\Schemas;

use App\Core\Contracts\SettingsRepository;
use App\Core\Plugins\Services\PluginContributions;
use App\Plugins\CatalogProducts\src\Contracts\ProductFormExtender;
use App\Plugins\CatalogProducts\src\Models\ExtraOption;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

final class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        /** @var list<Tab> $tabs */
        $tabs = [
            // TAB 1: BASIC INFO & MEDIA
            Tab::make('البيانات الأساسية')
                ->icon('heroicon-o-information-circle')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('name')
                            ->label('الاسم (إنجليزي)')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('name_ar')
                            ->label('الاسم بالعربية')
                            ->maxLength(255),
                        Select::make('category_id')
                            ->label('الفئة')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Toggle::make('is_active')
                            ->label('متاح للطلب (نشط)')
                            ->default(true),
                    ]),
                    Textarea::make('description')
                        ->label('الوصف')
                        ->rows(3)
                        ->maxLength(65535)
                        ->columnSpanFull(),
                    FileUpload::make('image')
                        ->label('صورة المنتج')
                        ->image()
                        ->imageEditor()
                        ->optimizeForWeb('products')
                        ->helperText('يفضل استخدام صور مربعة بأبعاد لا تقل عن 800x800 بكسل')
                        ->columnSpanFull(),
                ]),

            // TAB 2: PRICING & VARIANTS
            Tab::make('التسعير والتنويعات')
                ->icon('heroicon-o-currency-dollar')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('base_price')
                            ->label('السعر الأساسي')
                            ->numeric()
                            ->prefix(fn () => app()->bound(SettingsRepository::class) ? app(SettingsRepository::class)->get('currency', 'EGP') : 'EGP')
                            ->maxValue(99999.99)
                            ->required(),
                        TextInput::make('price_after_discount')
                            ->label('السعر بعد الخصم')
                            ->numeric()
                            ->prefix(fn () => app()->bound(SettingsRepository::class) ? app(SettingsRepository::class)->get('currency', 'EGP') : 'EGP')
                            ->maxValue(99999.99)
                            ->helperText('اتركه فارغاً إذا لم يكن هناك خصم نشط'),
                    ]),
                    Toggle::make('sell_by_weight')
                        ->label('بيع المنتج بالوزن')
                        ->default(false)
                        ->live(),
                ]),

            // TAB 3: EXTRAS & WEIGHTS (WITH PRODUCT-LEVEL SELECTION RULES)
            Tab::make('الخيارات الإضافية والأوزان')
                ->icon('heroicon-o-adjustments-horizontal')
                ->schema([
                    Section::make('مجموعة الإضافات وقواعد الاختيار')
                        ->description('تحديد الإضافات المسموحة لهذا المنتج وقواعد اختيار العميل')
                        ->schema([
                            Select::make('extra_option_id')
                                ->label('مجموعة الإضافات')
                                ->relationship('extraOption', 'name')
                                ->searchable()
                                ->preload()
                                ->live(),
                            // Empty = inherit the extra option group's rule; a value overrides it for this product only.
                            Grid::make(3)
                                ->visible(fn (callable $get) => filled($get('extra_option_id')))
                                ->schema([
                                    TextInput::make('min_selections')
                                        ->label('الحد الأدنى للاختيارات')
                                        ->numeric()
                                        ->minValue(0)
                                        ->placeholder(fn (callable $get) => (string) (ExtraOption::find($get('extra_option_id'))?->min_selections ?? 0))
                                        ->helperText('اتركه فارغاً لاستخدام قيمة مجموعة الإضافات (0 يعني اختياري)'),
                                    TextInput::make('max_selections')
                                        ->label('الحد الأقصى للاختيارات')
                                        ->numeric()
                                        ->minValue(1)
                                        ->placeholder(fn (callable $get) => (string) (ExtraOption::find($get('extra_option_id'))?->max_selections ?? 'بلا حد'))
                                        ->helperText('اتركه فارغاً لاستخدام قيمة مجموعة الإضافات'),
                                    ToggleButtons::make('allow_multiple')
                                        ->label('السماح باختيار أكثر من خيار')
                                        ->boolean('نعم', 'لا')
                                        ->grouped()
                                        ->helperText('بدون اختيار = حسب مجموعة الإضافات'),
                                ]),
                        ]),

                    Section::make('خيارات الوزن')
                        ->visible(fn (callable $get) => (bool) $get('sell_by_weight'))
                        ->schema([
                            Select::make('weight_options_id')
                                ->label('قالب أوزان المنتج')
                                ->relationship('weightOption', 'name')
                                ->searchable()
                                ->preload()
                                ->required(fn (callable $get) => (bool) $get('sell_by_weight')),
                        ]),
                ]),
        ];

        /** @var PluginContributions $pluginContributions */
        $pluginContributions = app(PluginContributions::class);
        foreach ($pluginContributions->active('catalog.product.form') as $extender) {
            if ($extender instanceof ProductFormExtender) {
                foreach ($extender->tabs() as $tab) {
                    $tabs[] = $tab;
                }
            }
        }

        return $schema
            ->components([
                Tabs::make('تفاصيل المنتج')
                    ->tabs($tabs)
                    ->columnSpanFull(),
            ]);
    }
}
