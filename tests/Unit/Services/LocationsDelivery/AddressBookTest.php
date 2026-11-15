<?php

declare(strict_types=1);

namespace Tests\Unit\Services\LocationsDelivery;

use App\Models\User;
use App\Plugins\LocationsDelivery\src\Contracts\AddressBook;
use App\Plugins\LocationsDelivery\src\Models\Address;
use App\Plugins\LocationsDelivery\src\Models\Area;
use App\Plugins\LocationsDelivery\src\Models\Governorate;
use App\Plugins\LocationsDelivery\src\Services\EloquentAddressBook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AddressBookTest extends TestCase
{
    use RefreshDatabase;

    private AddressBook $addressBook;

    protected function setUp(): void
    {
        parent::setUp();
        $this->addressBook = new EloquentAddressBook();
    }

    public function test_implements_address_book_contract(): void
    {
        $this->assertInstanceOf(AddressBook::class, $this->addressBook);
    }

    public function test_address_book_bound_in_container(): void
    {
        $resolved = $this->app->make(AddressBook::class);
        $this->assertInstanceOf(AddressBook::class, $resolved);
        $this->assertInstanceOf(EloquentAddressBook::class, $resolved);
    }

    public function test_snapshot_address_is_key_for_key_identical_to_old_order_repository_inline_code(): void
    {
        $user = User::factory()->create();
        $governorate = Governorate::factory()->create(['name' => 'Cairo']);
        $area = Area::factory()->create([
            'governorate_id' => $governorate->id,
            'name' => 'Maadi',
            'shipping_cost' => 20.0,
        ]);
        $address = Address::factory()->create([
            'user_id' => $user->id,
            'area_id' => $area->id,
            'street' => 'Road 9',
            'building' => '12',
            'floor' => '2',
            'apartment' => '4',
            'phone_number' => '01012345678',
        ]);

        // Old inline code from OrderRepository:
        $expectedAddress = Address::with(['area.governorate'])->find($address->id);
        $this->assertInstanceOf(Address::class, $expectedAddress);
        $expected = $expectedAddress->toArray();
        $expectedArea = $expectedAddress->area;
        if ($expectedArea instanceof Area) {
            $expected['area_name'] = $expectedArea->name;
            $expectedGov = $expectedArea->governorate;
            if ($expectedGov instanceof Governorate) {
                $expected['governorate_name'] = $expectedGov->name;
            }
        }

        $actual = $this->addressBook->snapshotAddress($address->id);

        $this->assertIsArray($actual);
        $this->assertSame($expected, $actual);
        $this->assertSame('Maadi', $actual['area_name']);
        $this->assertSame('Cairo', $actual['governorate_name']);
        $this->assertSame('Road 9', $actual['street']);
        $this->assertSame('01012345678', $actual['phone_number']);
    }

    public function test_snapshot_address_returns_null_when_missing_or_invalid(): void
    {
        $this->assertNull($this->addressBook->snapshotAddress(99999));
        $this->assertNull($this->addressBook->snapshotAddress(0));
        $this->assertNull($this->addressBook->snapshotAddress(-1));
    }

    public function test_area_labels_is_key_for_key_identical_to_old_order_repository_inline_code(): void
    {
        $governorate = Governorate::factory()->create(['name' => 'Alexandria']);
        $area = Area::factory()->create([
            'governorate_id' => $governorate->id,
            'name' => 'Smouha',
        ]);

        // Old inline code from OrderRepository:
        $areaModel = Area::with('governorate')->find($area->id);
        $this->assertInstanceOf(Area::class, $areaModel);
        /** @var array<string, string> $expected */
        $expected = [];
        if (! empty($areaModel->name)) {
            $expected['area_name'] = $areaModel->name;
        }
        $govModel = $areaModel->governorate;
        if ($govModel instanceof Governorate && ! empty($govModel->name)) {
            $expected['governorate_name'] = $govModel->name;
        }

        $actual = $this->addressBook->areaLabels($area->id);

        $this->assertSame($expected, $actual);
        $this->assertSame([
            'area_name' => 'Smouha',
            'governorate_name' => 'Alexandria',
        ], $actual);
    }

    public function test_area_labels_returns_empty_array_when_missing_or_invalid(): void
    {
        $this->assertSame([], $this->addressBook->areaLabels(99999));
        $this->assertSame([], $this->addressBook->areaLabels(0));
        $this->assertSame([], $this->addressBook->areaLabels(-1));
    }
}
