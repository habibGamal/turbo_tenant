<?php

declare(strict_types=1);

use App\Models\User;
use App\Plugins\CouponsPromotions\src\Filament\Resources\Coupons\CouponResource;
use App\Plugins\CouponsPromotions\src\Filament\Resources\Coupons\Pages\CreateCoupon;
use App\Plugins\CouponsPromotions\src\Filament\Resources\Coupons\Pages\ListCoupons;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create([
        'is_admin' => true,
    ]));
});

it('has correct navigation and labels configured for coupons', function () {
    expect(CouponResource::getNavigationGroup())->toBe('العروض والخصومات')
        ->and(CouponResource::getNavigationLabel())->toBe('الكوبونات')
        ->and(CouponResource::getNavigationSort())->toBe(1)
        ->and(CouponResource::getModelLabel())->toBe('كوبون')
        ->and(CouponResource::getPluralModelLabel())->toBe('الكوبونات')
        ->and(CouponResource::getRecordTitleAttribute())->toBe('code')
        ->and(CouponResource::getGloballySearchableAttributes())->toContain('code', 'name');
});

it('can render coupon list and create pages', function () {
    livewire(ListCoupons::class)
        ->assertOk();

    livewire(CreateCoupon::class)
        ->assertOk();
});

it('has expected coupon table columns', function (string $column) {
    livewire(ListCoupons::class)
        ->assertTableColumnExists($column);
})->with([
    'code',
    'name',
    'type',
    'value',
    'status',
    'free_shipping',
    'min_order_total',
    'is_active',
    'expiry_date',
    'usage_count',
    'total_consumed',
]);
