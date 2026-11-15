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

    public function test_address_request_validation_returns_422_envelope(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/locations-delivery/addresses', []);

        $response->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonStructure([
                'status',
                'message',
                'errors' => [
                    'area_id',
                    'phone_number',
                    'street',
                    'building',
                    'floor',
                    'apartment',
                ],
            ]);
    }

    public function test_address_policy_forbids_updating_other_users_address_with_403_envelope(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $branch = Branch::create(['name' => 'B1', 'link' => 'https://maps.example.com', 'is_active' => true]);
        $gov = Governorate::create(['name' => 'G1', 'is_active' => true, 'sort_order' => 1]);
        $area = Area::create(['name' => 'A1', 'shipping_cost' => 20.0, 'governorate_id' => $gov->id, 'branch_id' => $branch->id, 'is_active' => true, 'sort_order' => 1]);

        $address = Address::create([
            'user_id' => $owner->id,
            'area_id' => $area->id,
            'phone_number' => '+201012345678',
            'street' => 'Owner St',
            'building' => '1',
            'floor' => '1',
            'apartment' => '1',
        ]);

        $response = $this->actingAs($otherUser)->patchJson("/api/locations-delivery/addresses/{$address->id}", [
            'area_id' => $area->id,
            'phone_number' => '+201012345678',
            'street' => 'Hacked St',
            'building' => '2',
            'floor' => '2',
            'apartment' => '2',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('status', 'error')
            ->assertJsonStructure(['status', 'message']);
    }

    public function test_address_policy_forbids_deleting_other_users_address_with_403_envelope(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $branch = Branch::create(['name' => 'B1', 'link' => 'https://maps.example.com', 'is_active' => true]);
        $gov = Governorate::create(['name' => 'G1', 'is_active' => true, 'sort_order' => 1]);
        $area = Area::create(['name' => 'A1', 'shipping_cost' => 20.0, 'governorate_id' => $gov->id, 'branch_id' => $branch->id, 'is_active' => true, 'sort_order' => 1]);

        $address = Address::create([
            'user_id' => $owner->id,
            'area_id' => $area->id,
            'phone_number' => '+201012345678',
            'street' => 'Owner St',
            'building' => '1',
            'floor' => '1',
            'apartment' => '1',
        ]);

        $response = $this->actingAs($otherUser)->deleteJson("/api/locations-delivery/addresses/{$address->id}");

        $response->assertStatus(403)
            ->assertJsonPath('status', 'error')
            ->assertJsonStructure(['status', 'message']);

        $this->assertDatabaseHas('addresses', ['id' => $address->id]);
    }

    public function test_updating_or_deleting_unknown_address_returns_404_envelope(): void
    {
        $user = User::factory()->create();

        $patchResponse = $this->actingAs($user)->patchJson('/api/locations-delivery/addresses/999999', [
            'area_id' => 1,
            'phone_number' => '+201012345678',
            'street' => 'St',
            'building' => '1',
            'floor' => '1',
            'apartment' => '1',
        ]);

        $patchResponse->assertStatus(404)
            ->assertJsonPath('status', 'error')
            ->assertJsonStructure(['status', 'message']);

        $deleteResponse = $this->actingAs($user)->deleteJson('/api/locations-delivery/addresses/999999');

        $deleteResponse->assertStatus(404)
            ->assertJsonPath('status', 'error')
            ->assertJsonStructure(['status', 'message']);
    }

    public function test_egyptian_phone_regex_validation(): void
    {
        $user = User::factory()->create();
        $branch = Branch::create(['name' => 'B1', 'link' => 'https://maps.example.com', 'is_active' => true]);
        $gov = Governorate::create(['name' => 'G1', 'is_active' => true, 'sort_order' => 1]);
        $area = Area::create(['name' => 'A1', 'shipping_cost' => 20.0, 'governorate_id' => $gov->id, 'branch_id' => $branch->id, 'is_active' => true, 'sort_order' => 1]);

        $invalidResponse = $this->actingAs($user)->postJson('/api/locations-delivery/addresses', [
            'area_id' => $area->id,
            'phone_number' => '01012345678', // missing +20
            'street' => 'Tahrir St',
            'building' => '10',
            'floor' => '3',
            'apartment' => '5',
        ]);

        $invalidResponse->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('errors.phone_number.0', 'رقم الهاتف يجب أن يكون رقم مصري صحيح.');
    }
}
