<?php

declare(strict_types=1);

namespace App\Plugins\CouponsPromotions\src\Filament\Resources\Coupons\Pages;

use App\Plugins\CouponsPromotions\src\Filament\Resources\Coupons\CouponResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditCoupon extends EditRecord
{
    protected static string $resource = CouponResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
