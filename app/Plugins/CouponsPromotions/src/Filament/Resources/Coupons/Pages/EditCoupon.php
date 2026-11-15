<?php

declare(strict_types=1);

namespace App\Plugins\CouponsPromotions\src\Filament\Resources\Coupons\Pages;

use App\Plugins\CouponsPromotions\src\Enums\ApplicableTo;
use App\Plugins\CouponsPromotions\src\Filament\Resources\Coupons\CouponResource;
use App\Plugins\CouponsPromotions\src\Filament\Resources\Coupons\Schemas\CouponForm;
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

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['conditions']['applicable_to']['type'] ?? null) === ApplicableTo::LINE_TYPES->value) {
            $provider = CouponForm::getActiveLineReferenceProvider();
            $data['conditions']['applicable_to']['line_type'] = $provider?->lineType()
                ?? $data['conditions']['applicable_to']['line_type']
                ?? 'package';
            $ids = $data['conditions']['applicable_to']['ids'] ?? [];
            $data['conditions']['applicable_to']['ids'] = is_array($ids)
                ? array_values(array_map('intval', $ids))
                : [];
        }

        return $data;
    }
}
