<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use App\Plugins\LocationsDelivery\src\Models\Address;
use App\Plugins\LocationsDelivery\src\Models\Area;
use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\LocationsDelivery\src\Models\Governorate;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

final class LocationsDeliveryApiTest extends TestCase
{
    public function test_locations_delivery_api_endpoints(): void
    {
        $branch = Branch::create([
            'name' => 'Main Branch',
            'link' => 'https://maps.example.com',
            'is_active' => true,
        ]);

        $gov = Governorate::create([
            'name' => 'Cairo',
            'name_ar' => 'القاهرة',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $area = Area::create([
            'name' => 'Maadi',
            'name_ar' => 'المعادي',
            'shipping_cost' => 30.0,
            'governorate_id' => $gov->id,
            'branch_id' => $branch->id,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // 1. Test aggregate GET /api/locations-delivery
        $response = $this->getJson('/api/locations-delivery');
        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'status',
                'data' => [
                    'addresses',
                    'governorates',
                    'branches',
                ],
            ]);

        // 2. Test GET /api/locations-delivery/governorates
        $govResponse = $this->getJson('/api/locations-delivery/governorates');
        $govResponse->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.0.name', 'Cairo')
            ->assertJsonPath('data.0.areas.0.name', 'Maadi');

        // 3. Test GET /api/locations-delivery/branches
        $branchResponse = $this->getJson('/api/locations-delivery/branches');
        $branchResponse->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.0.name', 'Main Branch');

        // 4. Test Unauthenticated check
        Auth::logout();
        $unauthResponse = $this->getJson('/api/locations-delivery/addresses');
        $unauthResponse->assertStatus(401);

        // 5. Authenticate user and manage addresses
        $user = User::factory()->create();

        // Create Address
        $createResponse = $this->actingAs($user)->postJson('/api/locations-delivery/addresses', [
            'area_id' => $area->id,
            'phone_number' => '+201012345678',
            'street' => 'Tahrir St',
            'building' => '10',
            'floor' => '3',
            'apartment' => '5',
            'notes' => 'Near metro station',
            'is_default' => true,
        ]);

        $createResponse->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.street', 'Tahrir St');

        $addressId = $createResponse->json('data.id');

        // List Addresses
        $listResponse = $this->actingAs($user)->getJson('/api/locations-delivery/addresses');
        $listResponse->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(1, 'data');

        // Update Address
        $updateResponse = $this->actingAs($user)->patchJson("/api/locations-delivery/addresses/{$addressId}", [
            'area_id' => $area->id,
            'phone_number' => '+201012345678',
            'street' => 'Updated St',
            'building' => '12',
            'floor' => '4',
            'apartment' => '6',
            'is_default' => true,
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.street', 'Updated St');

        // Delete Address
        $deleteResponse = $this->actingAs($user)->deleteJson("/api/locations-delivery/addresses/{$addressId}");
        $deleteResponse->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseMissing('addresses', ['id' => $addressId]);
    }
}
