<?php

declare(strict_types=1);

namespace App\Filament\Resources\Coupons\Schemas;

use App\Models\Area;
use App\Models\Category;
use App\Models\Governorate;
use App\Models\Product;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
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
                Section::make('Basic Information')
                    ->schema([
                        TextInput::make('code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->columnSpan(1),
                        TextInput::make('name')
                            ->maxLength(255)
                            ->columnSpan(1),
                        Textarea::make('description')
                            ->rows(2)
                            ->maxLength(65535)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Discount Configuration')
                    ->schema([
                        Select::make('type')
                            ->required()
                            ->options([
                                'percentage' => 'Percentage',
                                'fixed' => 'Fixed Amount',
                            ])
                            ->default('percentage')
                            ->live()
                            ->columnSpan(1),
                        TextInput::make('value')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->suffix(fn(Get $get) => $get('type') === 'percentage' ? '%' : 'EGP')
                            ->columnSpan(1),
                        DateTimePicker::make('expiry_date')
                            ->required()
                            ->minDate(now())
                            ->columnSpan(1),
                        Toggle::make('is_active')
                            ->default(true)
                            ->columnSpan(1),
                        TextInput::make('max_usage')
                            ->numeric()
                            ->minValue(1)
                            ->label('Maximum Total Usage')
                            ->hint('Leave empty for unlimited')
                            ->columnSpan(1),
                    ])
                    ->columns(2),

                Section::make('Order Value Restrictions')
                    ->schema([
                        TextInput::make('conditions.min_order_total')
                            ->numeric()
                            ->minValue(0)
                            ->label('Minimum Order Total')
                            ->suffix('EGP')
                            ->hint('Leave empty for no minimum')
                            ->columnSpan(1),
                        TextInput::make('conditions.max_order_total')
                            ->numeric()
                            ->minValue(0)
                            ->label('Maximum Order Total')
                            ->suffix('EGP')
                            ->hint('Leave empty for no maximum')
                            ->columnSpan(1),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('Applicable Products/Categories')
                    ->schema([
                        Select::make('conditions.applicable_to.type')
                            ->label('Apply To')
                            ->options([
                                'all' => 'All Products',
                                'products' => 'Specific Products',
                                'categories' => 'Specific Categories',
                            ])
                            ->default('all')
                            ->live()
                            ->required(),
                        Select::make('conditions.applicable_to.product_ids')
                            ->label('Select Products')
                            ->multiple()
                            ->searchable()
                            ->options(Product::where('is_active', true)->pluck('name', 'id'))
                            ->visible(fn(Get $get) => $get('conditions.applicable_to.type') === 'products'),
                        Select::make('conditions.applicable_to.category_ids')
                            ->label('Select Categories')
                            ->multiple()
                            ->searchable()
                            ->options(Category::where('is_active', true)->pluck('name', 'id'))
                            ->visible(fn(Get $get) => $get('conditions.applicable_to.type') === 'categories'),
                    ])
                    ->collapsible(),

                Section::make('Shipping Conditions')
                    ->schema([
                        Toggle::make('conditions.shipping.free_shipping')
                            ->label('Enable Free Shipping')
                            ->live()
                            ->columnSpan(1),
                        TextInput::make('conditions.shipping.free_shipping_threshold')
                            ->label('Free Shipping Threshold')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('EGP')
                            ->hint('Order total required for free shipping')
                            ->visible(fn(Get $get) => $get('conditions.shipping.free_shipping'))
                            ->columnSpan(1),
                        Select::make('conditions.shipping.applicable_governorates')
                            ->label('Applicable Governorates')
                            ->multiple()
                            ->searchable()
                            ->options(Governorate::where('is_active', true)->pluck('name', 'id'))
                            ->hint('Leave empty for all governorates')
                            ->columnSpanFull(),
                        Select::make('conditions.shipping.applicable_areas')
                            ->label('Applicable Areas')
                            ->multiple()
                            ->searchable()
                            ->options(Area::where('is_active', true)->pluck('name', 'id'))
                            ->hint('Leave empty for all areas')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('User Restrictions')
                    ->schema([
                        Toggle::make('conditions.usage_restrictions.first_order_only')
                            ->label('First Order Only')
                            ->hint('Coupon only valid for users who have never placed an order')
                            ->columnSpan(1),
                        Toggle::make('conditions.usage_restrictions.user_specific')
                            ->label('Specific Users Only')
                            ->live()
                            ->columnSpan(1),
                        Select::make('conditions.usage_restrictions.user_ids')
                            ->label('Select Users')
                            ->multiple()
                            ->searchable()
                            ->options(User::all()->pluck('name', 'id'))
                            ->visible(fn(Get $get) => $get('conditions.usage_restrictions.user_specific'))
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('Time Restrictions')
                    ->schema([
                        Select::make('conditions.valid_days')
                            ->label('Valid Days of Week')
                            ->multiple()
                            ->options([
                                0 => 'Sunday',
                                1 => 'Monday',
                                2 => 'Tuesday',
                                3 => 'Wednesday',
                                4 => 'Thursday',
                                5 => 'Friday',
                                6 => 'Saturday',
                            ])
                            ->hint('Leave empty for all days')
                            ->columnSpanFull(),
                        TimePicker::make('conditions.valid_hours.start')
                            ->label('Valid From Time')
                            ->seconds(false)
                            ->columnSpan(1),
                        TimePicker::make('conditions.valid_hours.end')
                            ->label('Valid Until Time')
                            ->seconds(false)
                            ->columnSpan(1),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('Usage Statistics')
                    ->schema([
                        TextInput::make('usage_count')
                            ->label('Times Used')
                            ->numeric()
                            ->default(0)
                            ->disabled()
                            ->columnSpan(1),
                        TextInput::make('total_consumed')
                            ->label('Total Discount Given')
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
