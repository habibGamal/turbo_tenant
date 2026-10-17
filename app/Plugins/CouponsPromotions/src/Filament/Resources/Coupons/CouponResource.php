<?php

declare(strict_types=1);

namespace App\Plugins\CouponsPromotions\src\Filament\Resources\Coupons;

use App\Plugins\CouponsPromotions\src\Filament\Resources\Coupons\Pages\CreateCoupon;
use App\Plugins\CouponsPromotions\src\Filament\Resources\Coupons\Pages\EditCoupon;
use App\Plugins\CouponsPromotions\src\Filament\Resources\Coupons\Pages\ListCoupons;
use App\Plugins\CouponsPromotions\src\Filament\Resources\Coupons\Schemas\CouponForm;
use App\Plugins\CouponsPromotions\src\Filament\Resources\Coupons\Tables\CouponsTable;
use App\Plugins\CouponsPromotions\src\Models\Coupon;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

final class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'الكوبونات';

    protected static ?string $modelLabel = 'كوبون';

    protected static ?string $pluralModelLabel = 'الكوبونات';

    public static function form(Schema $schema): Schema
    {
        return CouponForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CouponsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCoupons::route('/'),
            'create' => CreateCoupon::route('/create'),
            'edit' => EditCoupon::route('/{record}/edit'),
        ];
    }
}
