<?php

declare(strict_types=1);

namespace Tests\Unit\Services\LocationsDelivery;

use App\Models\User;
use App\Plugins\LocationsDelivery\src\Contracts\DeliveryQuote;
use App\Plugins\LocationsDelivery\src\Models\Address;
use App\Plugins\LocationsDelivery\src\Models\Area;
use App\Plugins\LocationsDelivery\src\Models\Governorate;
use App\Plugins\LocationsDelivery\src\Services\EloquentDeliveryQuote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DeliveryQuoteTest extends TestCase
{
    use RefreshDatabase;

    private DeliveryQuote $quote;

    protected function setUp(): void
    {
        parent::setUp();
        $this->quote = new EloquentDeliveryQuote();
    }

    public function test_implements_delivery_quote_contract(): void
    {
        $this->assertInstanceOf(DeliveryQuote::class, $this->quote);
    }

    public function test_delivery_quote_bound_in_container(): void
    {
        $resolved = $this->app->make(DeliveryQuote::class);
        $this->assertInstanceOf(DeliveryQuote::class, $resolved);
        $this->assertInstanceOf(EloquentDeliveryQuote::class, $resolved);
    }

    public function test_fee_for_area_returns_shipping_cost_when_present(): void
    {
        $governorate = Governorate::factory()->create();
        $area = Area::factory()->create([
            'governorate_id' => $governorate->id,
            'shipping_cost' => 25.50,
        ]);

        $fee = $this->quote->feeForArea($area->id);

        $this->assertSame(25.50, $fee);
    }

    public function test_fee_for_area_returns_zero_when_missing_or_invalid(): void
    {
        $this->assertSame(0.0, $this->quote->feeForArea(99999));
        $this->assertSame(0.0, $this->quote->feeForArea(0));
        $this->assertSame(0.0, $this->quote->feeForArea(-1));
    }

    public function test_fee_for_area_returns_zero_when_shipping_cost_is_zero(): void
    {
        $governorate = Governorate::factory()->create();
        $area = Area::factory()->create([
            'governorate_id' => $governorate->id,
            'shipping_cost' => 0.0,
        ]);

        $fee = $this->quote->feeForArea($area->id);

        $this->assertSame(0.0, $fee);
    }

    public function test_fee_for_address_returns_area_shipping_cost(): void
    {
        $user = User::factory()->create();
        $governorate = Governorate::factory()->create();
        $area = Area::factory()->create([
            'governorate_id' => $governorate->id,
            'shipping_cost' => 35.00,
        ]);
        $address = Address::factory()->create([
            'user_id' => $user->id,
            'area_id' => $area->id,
        ]);

        $fee = $this->quote->feeForAddress($address->id);

        $this->assertSame(35.00, $fee);
    }

    public function test_fee_for_address_returns_zero_when_missing_or_invalid(): void
    {
        $this->assertSame(0.0, $this->quote->feeForAddress(99999));
        $this->assertSame(0.0, $this->quote->feeForAddress(0));
        $this->assertSame(0.0, $this->quote->feeForAddress(-1));
    }

    public function test_fee_for_address_returns_zero_when_area_has_zero_shipping_cost(): void
    {
        $user = User::factory()->create();
        $governorate = Governorate::factory()->create();
        $area = Area::factory()->create([
            'governorate_id' => $governorate->id,
            'shipping_cost' => 0.0,
        ]);
        $address = Address::factory()->create([
            'user_id' => $user->id,
            'area_id' => $area->id,
        ]);

        $fee = $this->quote->feeForAddress($address->id);

        $this->assertSame(0.0, $fee);
    }
}
