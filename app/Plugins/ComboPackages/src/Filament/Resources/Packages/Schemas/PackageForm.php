<?php

declare(strict_types=1);

namespace App\Plugins\ComboPackages\src\Filament\Resources\Packages\Schemas;

use App\Core\Plugins\Contracts\FormComponentContributor;
use App\Core\Plugins\Services\PluginContributions;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Models\ProductVariant;
use App\Plugins\ComboPackages\src\Services\PackagePriceEstimator;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

final class PackageForm
{
    /** @var array<int, string> */
    private static array $productLabelsCache = [];

    /** @var array<int, string> */
    private static array $variantLabelsCache = [];

    public static function getProductLabel(?int $productId): string
    {
        if (! $productId) {
            return 'عنصر';
        }

        return self::$productLabelsCache[$productId] ??= (
            Product::find($productId)?->name ?? 'عنصر'
        );
    }

    public static function getVariantLabel(?int $variantId): ?string
    {
        if (! $variantId) {
            return null;
        }

        return self::$variantLabelsCache[$variantId] ??= (
            ProductVariant::find($variantId)?->name
        );
    }

    /**
     * @param  array<int>  $productIds
     */
    public static function preloadProductLabels(array $productIds): void
    {
        $missingIds = array_diff($productIds, array_keys(self::$productLabelsCache));
        if (! empty($missingIds)) {
            $products = Product::whereIn('id', $missingIds)->pluck('name', 'id')->all();
            foreach ($products as $id => $name) {
                self::$productLabelsCache[(int) $id] = (string) $name;
            }
        }
    }

    public static function recomputeDiscount(callable $get, callable $set): void
    {
        $price = (float) ($get('price') ?? 0);
        $originalInput = $get('original_price');

        if ($originalInput === null || $originalInput === '') {
            $rawState = ['groups' => $get('groups') ?? []];
            $estimate = app(PackagePriceEstimator::class)->estimate($rawState);
            $original = $estimate > 0 ? $estimate : 0.0;
        } else {
            $original = (float) $originalInput;
        }

        if ($original > 0 && $original > $price) {
            $set('discount_percentage', (int) round((($original - $price) / $original) * 100));
        } else {
            $set('discount_percentage', null);
        }
    }

    public static function configure(Schema $schema): Schema
    {
        $productClass = Product::class;
        $variantClass = ProductVariant::class;

        $imageUpload = FileUpload::make('image')
            ->label('صورة الباقة')
            ->image()
            ->imageEditor()
            ->helperText('يفضل استخدام صور مربعة بأبعاد لا تقل عن 800x800 بكسل')
            ->columnSpanFull();

        if (FileUpload::hasMacro('optimizeForWeb')) {
            $imageUpload->optimizeForWeb('packages');
        } else {
            $imageUpload->directory('images/packages');
        }

        $tabs = [
            Tab::make('البيانات والتسعير')
                ->icon('heroicon-o-cube')
                ->schema([
                    Section::make('معلومات أساسية')
                        ->schema([
                            TextInput::make('name')
                                ->required(fn (callable $get) => blank($get('name_ar')))
                                ->maxLength(255)
                                ->label('الاسم')
                                ->live(onBlur: true),

                            TextInput::make('name_ar')
                                ->required(fn (callable $get) => blank($get('name')))
                                ->maxLength(255)
                                ->label('الاسم (عربي)')
                                ->live(onBlur: true),

                            Textarea::make('description')
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
                            Placeholder::make('alacarte_estimate')
                                ->label('القيمة الإفرادية المقدرة (À la carte)')
                                ->content(function (callable $get): string {
                                    $groups = $get('groups') ?? [];
                                    $estimate = app(PackagePriceEstimator::class)->estimate(['groups' => $groups]);

                                    return $estimate > 0
                                        ? number_format($estimate, 2).' EGP'
                                        : '0.00 EGP (تتحدث مع إضافة العناصر)';
                                })
                                ->helperText('مجموع أسعار المنتجات المكونة للباقة بالأسعار الفردية')
                                ->columnSpanFull(),

                            TextInput::make('price')
                                ->required()
                                ->numeric()
                                ->prefix('EGP')
                                ->minValue(0.01)
                                ->label('سعر الباقة')
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (callable $get, callable $set) {
                                    self::recomputeDiscount($get, $set);
                                })
                                ->hint(function (callable $get): ?string {
                                    $price = (float) ($get('price') ?? 0);
                                    $estimate = app(PackagePriceEstimator::class)->estimate(['groups' => $get('groups') ?? []]);
                                    if ($estimate > 0 && $price >= $estimate) {
                                        return 'تنبيه: سعر الباقة أكبر من أو يساوي القيمة الإفرادية المقدرة ('.number_format($estimate, 2).' EGP)';
                                    }

                                    return null;
                                })
                                ->hintColor('warning'),

                            TextInput::make('original_price')
                                ->numeric()
                                ->prefix('EGP')
                                ->minValue(0)
                                ->label('السعر الأصلي')
                                ->placeholder(function (callable $get): string {
                                    $estimate = app(PackagePriceEstimator::class)->estimate(['groups' => $get('groups') ?? []]);

                                    return $estimate > 0 ? (string) $estimate : 'تلقائي (القيمة المقدرة)';
                                })
                                ->helperText('اتركه فارغاً ليتم استخدام القيمة المقدرة تلقائياً')
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (callable $get, callable $set) {
                                    self::recomputeDiscount($get, $set);
                                }),

                            TextInput::make('discount_percentage')
                                ->numeric()
                                ->suffix('%')
                                ->disabled()
                                ->dehydrated()
                                ->label('نسبة الخصم')
                                ->helperText('تحسب تلقائياً من الفارق بين السعر الأصلي وسعر الباقة')
                                ->placeholder('تلقائي'),

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

                            Toggle::make('allow_coupons')
                                ->default(true)
                                ->label('السماح باستخدام كوبونات الخصم'),
                        ])
                        ->columns(3),
                ]),

            Tab::make('المظهر والتصميم')
                ->icon('heroicon-o-paint-brush')
                ->schema([
                    Section::make('صورة الباقة')
                        ->schema([
                            $imageUpload,
                        ]),

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
                                ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                                    $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

                                    return $data;
                                })
                                ->mutateRelationshipDataBeforeSaveUsing(function (array $data): array {
                                    $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

                                    return $data;
                                })
                                ->schema([
                                    TextInput::make('name')
                                        ->label('اسم المجموعة')
                                        ->placeholder('مثل: اختر طبقك الرئيسي')
                                        ->live(onBlur: true),

                                    TextInput::make('name_ar')
                                        ->label('اسم المجموعة (عربي)')
                                        ->live(onBlur: true),

                                    Textarea::make('description')
                                        ->rows(2)
                                        ->label('وصف المجموعة')
                                        ->columnSpanFull(),

                                    Textarea::make('description_ar')
                                        ->rows(2)
                                        ->label('وصف المجموعة (عربي)')
                                        ->columnSpanFull(),

                                    Select::make('selection_type')
                                        ->required()
                                        ->options([
                                            'all' => 'جميع العناصر (ثابت)',
                                            'choose_one' => 'اختر واحد',
                                            'choose_multiple' => 'اختر متعدد',
                                        ])
                                        ->default('all')
                                        ->live()
                                        ->afterStateUpdated(function ($state, callable $set) {
                                            if ($state === 'choose_one') {
                                                $set('min_selections', 1);
                                                $set('max_selections', 1);
                                                $set('allow_repeats', false);
                                            } elseif ($state === 'all') {
                                                $set('min_selections', null);
                                                $set('max_selections', null);
                                                $set('allow_repeats', false);
                                            }
                                        })
                                        ->label('نوع الاختيار'),

                                    Toggle::make('allow_repeats')
                                        ->default(false)
                                        ->label('السماح بتكرار الاختيار')
                                        ->helperText('يمكن للعميل اختيار نفس العنصر أكثر من مرة')
                                        ->visible(fn (callable $get) => $get('selection_type') === 'choose_multiple')
                                        ->live(),

                                    TextInput::make('min_selections')
                                        ->numeric()
                                        ->minValue(0)
                                        ->label('الحد الأدنى للاختيارات')
                                        ->disabled(fn (callable $get) => $get('selection_type') === 'choose_one')
                                        ->dehydrated()
                                        ->visible(fn (callable $get) => in_array($get('selection_type'), ['choose_one', 'choose_multiple'], true))
                                        ->live(onBlur: true),

                                    TextInput::make('max_selections')
                                        ->numeric()
                                        ->minValue(0)
                                        ->label('الحد الأقصى للاختيارات')
                                        ->disabled(fn (callable $get) => $get('selection_type') === 'choose_one')
                                        ->dehydrated()
                                        ->visible(fn (callable $get) => in_array($get('selection_type'), ['choose_one', 'choose_multiple'], true))
                                        ->live(onBlur: true),

                                    TextInput::make('sort_order')
                                        ->numeric()
                                        ->default(0)
                                        ->label('ترتيب العرض'),

                                    Repeater::make('items')
                                        ->relationship()
                                        ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                                            $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
                                            $data['quantity'] = max(1, (int) ($data['quantity'] ?? 1));
                                            $data['price_adjustment'] = (float) ($data['price_adjustment'] ?? 0);
                                            $data['variant_mode'] = (string) ($data['variant_mode'] ?? 'fixed');
                                            $data['allow_extras'] = (bool) ($data['allow_extras'] ?? false);
                                            if ($data['variant_mode'] === 'customer_choice') {
                                                $data['variant_id'] = null;
                                            }

                                            return $data;
                                        })
                                        ->mutateRelationshipDataBeforeSaveUsing(function (array $data): array {
                                            $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
                                            $data['quantity'] = max(1, (int) ($data['quantity'] ?? 1));
                                            $data['price_adjustment'] = (float) ($data['price_adjustment'] ?? 0);
                                            $data['variant_mode'] = (string) ($data['variant_mode'] ?? 'fixed');
                                            $data['allow_extras'] = (bool) ($data['allow_extras'] ?? false);
                                            if ($data['variant_mode'] === 'customer_choice') {
                                                $data['variant_id'] = null;
                                            }

                                            return $data;
                                        })
                                        ->schema([
                                            Select::make('product_id')
                                                ->required()
                                                ->label('المنتج')
                                                ->searchable()
                                                ->getSearchResultsUsing(function (string $search) use ($productClass): array {
                                                    return $productClass::query()
                                                        ->where('is_active', true)
                                                        ->where(function ($q) {
                                                            $q->where('sell_by_weight', false)->orWhereNull('sell_by_weight');
                                                        })
                                                        ->when(filled($search), function ($q) use ($search) {
                                                            $q->where(function ($sub) use ($search) {
                                                                $sub->where('name', 'like', "%{$search}%")
                                                                    ->orWhere('name_ar', 'like', "%{$search}%");
                                                            });
                                                        })
                                                        ->limit(50)
                                                        ->pluck('name', 'id')
                                                        ->all();
                                                })
                                                ->getOptionLabelUsing(fn ($value) => self::getProductLabel($value ? (int) $value : null))
                                                ->live()
                                                ->afterStateUpdated(function ($state, callable $set) use ($productClass) {
                                                    $set('variant_id', null);
                                                    if ($state) {
                                                        $set('_product_name', self::getProductLabel((int) $state));
                                                        $hasExtra = $productClass::query()->where('id', $state)->whereNotNull('extra_option_id')->exists();
                                                        if (! $hasExtra) {
                                                            $set('allow_extras', false);
                                                        }
                                                    } else {
                                                        $set('allow_extras', false);
                                                    }
                                                }),

                                            Select::make('variant_mode')
                                                ->label('نوع التنويع')
                                                ->options([
                                                    'fixed' => 'ثابت',
                                                    'customer_choice' => 'يختار العميل',
                                                ])
                                                ->default('fixed')
                                                ->live()
                                                ->afterStateUpdated(function ($state, callable $set) {
                                                    if ($state === 'customer_choice') {
                                                        $set('variant_id', null);
                                                    }
                                                }),

                                            Select::make('variant_id')
                                                ->label('تنويع (اختياري)')
                                                ->searchable()
                                                ->visible(fn (callable $get) => ($get('variant_mode') ?? 'fixed') === 'fixed')
                                                ->getSearchResultsUsing(function (string $search, callable $get) use ($variantClass): array {
                                                    $productId = $get('product_id');
                                                    if (! $productId) {
                                                        return [];
                                                    }

                                                    return $variantClass::query()
                                                        ->where('product_id', $productId)
                                                        ->where('is_available', true)
                                                        ->when(filled($search), function ($q) use ($search) {
                                                            $q->where(function ($sub) use ($search) {
                                                                $sub->where('name', 'like', "%{$search}%")
                                                                    ->orWhere('name_ar', 'like', "%{$search}%");
                                                            });
                                                        })
                                                        ->limit(50)
                                                        ->pluck('name', 'id')
                                                        ->all();
                                                })
                                                ->getOptionLabelUsing(fn ($value) => self::getVariantLabel($value ? (int) $value : null))
                                                ->live(),

                                            TextInput::make('quantity')
                                                ->required()
                                                ->numeric()
                                                ->default(1)
                                                ->minValue(1)
                                                ->label('الكمية')
                                                ->live(onBlur: true),

                                            TextInput::make('price_adjustment')
                                                ->numeric()
                                                ->default(0)
                                                ->prefix('EGP')
                                                ->label('تعديل السعر')
                                                ->helperText('رسوم إضافية لهذا الخيار'),

                                            Toggle::make('allow_extras')
                                                ->label('السماح بالإضافات')
                                                ->default(false)
                                                ->visible(function (callable $get) use ($productClass): bool {
                                                    $productId = $get('product_id');
                                                    if (! $productId) {
                                                        return false;
                                                    }

                                                    return $productClass::query()->where('id', $productId)->whereNotNull('extra_option_id')->exists();
                                                })
                                                ->live(),

                                            Toggle::make('is_default')
                                                ->label('الاختيار الافتراضي')
                                                ->helperText('محدد مسبقاً للمجموعات الشرطية')
                                                ->visible(fn (callable $get) => in_array($get('../../selection_type'), ['choose_one', 'choose_multiple'], true))
                                                ->live(),

                                            TextInput::make('sort_order')
                                                ->numeric()
                                                ->default(0)
                                                ->label('ترتيب العرض'),
                                        ])
                                        ->columns(3)
                                        ->defaultItems(1)
                                        ->addActionLabel('إضافة عنصر')
                                        ->collapsible()
                                        ->itemLabel(function (array $state): ?string {
                                            if (! empty($state['_product_name'])) {
                                                return $state['_product_name'];
                                            }
                                            $productId = isset($state['product_id']) ? (int) $state['product_id'] : null;

                                            return self::getProductLabel($productId);
                                        })
                                        ->columnSpanFull(),
                                ])
                                ->columns(3)
                                ->defaultItems(1)
                                ->addActionLabel('إضافة مجموعة')
                                ->collapsible()
                                ->itemLabel(fn (array $state): ?string => $state['name'] ?? $state['name_ar'] ?? 'مجموعة')
                                ->reorderable()
                                ->cloneable(),
                        ])
                        ->columnSpanFull()
                        ->description('حدد مجموعات وعناصر الباقة. المجموعات يمكن أن تكون ثابتة (جميع العناصر) أو شرطية (اختر واحداً/متعدد).'),
                ]),

            Tab::make('قواعد البيع')
                ->icon('heroicon-o-adjustments-horizontal')
                ->schema([
                    Section::make('أوقات التوفر')
                        ->description('حدد أوقات وأيام توفر الباقة. تركها فارغة يعني توفر الباقة دائماً.')
                        ->schema([
                            Repeater::make('availabilities')
                                ->relationship('availabilities')
                                ->schema([
                                    Select::make('day_of_week')
                                        ->label('اليوم')
                                        ->options([
                                            0 => 'الأحد',
                                            1 => 'الإثنين',
                                            2 => 'الثلاثاء',
                                            3 => 'الأربعاء',
                                            4 => 'الخميس',
                                            5 => 'الجمعة',
                                            6 => 'السبت',
                                        ])
                                        ->required(),

                                    TimePicker::make('starts_at')
                                        ->label('من')
                                        ->required(),

                                    TimePicker::make('ends_at')
                                        ->label('إلى')
                                        ->required(),
                                ])
                                ->columns(3)
                                ->addActionLabel('إضافة فترة توفر')
                                ->defaultItems(0)
                                ->columnSpanFull(),
                        ]),

                    Section::make('الفروع وأنواع الطلبات')
                        ->schema([
                            Select::make('branches')
                                ->relationship('branches', 'name')
                                ->multiple()
                                ->preload()
                                ->label('الفروع المتاحة')
                                ->helperText('اتركه فارغاً للإتاحة في جميع الفروع'),

                            CheckboxList::make('allowed_order_types')
                                ->label('أنواع الطلبات المسموحة')
                                ->options([
                                    'web_delivery' => 'توصيل',
                                    'web_takeaway' => 'استلام',
                                ])
                                ->helperText('اتركه فارغاً للسماح بجميع أنواع الطلبات'),
                        ])
                        ->columns(2),

                    Section::make('حدود الطلب')
                        ->schema([
                            TextInput::make('max_per_order')
                                ->label('الحد الأقصى لكل طلب')
                                ->numeric()
                                ->minValue(1)
                                ->helperText('اتركه فارغاً لطلب كميات غير محدودة'),

                            TextInput::make('daily_limit')
                                ->label('الحد اليومي')
                                ->numeric()
                                ->minValue(1)
                                ->helperText('اتركه فارغاً لكميات غير محدودة يومياً'),
                        ])
                        ->columns(2),
                ]),
        ];

        /** @var PluginContributions $pluginContributions */
        $pluginContributions = app(PluginContributions::class);
        $contributedComponents = [];
        foreach ($pluginContributions->active('admin.form.combo-packages.package') as $contributor) {
            if ($contributor instanceof FormComponentContributor) {
                foreach ($contributor->components() as $component) {
                    $contributedComponents[] = $component;
                }
            }
        }

        if (! empty($contributedComponents)) {
            $tabs[] = Tab::make('تكاملات')
                ->schema($contributedComponents);
        }

        return $schema
            ->components([
                Tabs::make('بيانات الباقة')
                    ->tabs($tabs)
                    ->columnSpanFull(),
            ]);
    }
}
