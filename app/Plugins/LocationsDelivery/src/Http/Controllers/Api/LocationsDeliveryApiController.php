<?php

declare(strict_types=1);

namespace App\Plugins\LocationsDelivery\src\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Plugins\LocationsDelivery\src\Http\Requests\AddressRequest;
use App\Plugins\LocationsDelivery\src\Http\Resources\AddressResource;
use App\Plugins\LocationsDelivery\src\Http\Resources\BranchResource;
use App\Plugins\LocationsDelivery\src\Http\Resources\GovernorateResource;
use App\Plugins\LocationsDelivery\src\Models\Address;
use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\LocationsDelivery\src\Models\Governorate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class LocationsDeliveryApiController extends Controller
{
    /**
     * Get aggregate delivery locations metadata and customer addresses if logged in.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $addresses = $user
            ? AddressResource::collection($user->addresses()->with(['area.governorate'])->latest()->get())
            : [];

        $governorates = GovernorateResource::collection(
            Governorate::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->with(['areas' => function ($query) {
                    $query->where('is_active', true)->orderBy('sort_order');
                }])
                ->get()
        );

        $branches = BranchResource::collection(
            Branch::query()
                ->where('is_active', true)
                ->get()
        );

        return ApiResponse::success([
            'addresses' => $addresses,
            'governorates' => $governorates,
            'branches' => $branches,
        ]);
    }

    /**
     * Get active governorates with delivery areas.
     */
    public function governorates(Request $request): JsonResponse
    {
        $governorates = Governorate::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with(['areas' => function ($query) {
                $query->where('is_active', true)->orderBy('sort_order');
            }])
            ->get();

        return ApiResponse::success(GovernorateResource::collection($governorates));
    }

    /**
     * Get active branches.
     */
    public function branches(Request $request): JsonResponse
    {
        $branches = Branch::query()
            ->where('is_active', true)
            ->get();

        return ApiResponse::success(BranchResource::collection($branches));
    }

    /**
     * Get user addresses.
     */
    public function addresses(Request $request): JsonResponse
    {
        $addresses = $request->user()->addresses()
            ->with(['area.governorate'])
            ->latest()
            ->get();

        return ApiResponse::success(AddressResource::collection($addresses));
    }

    /**
     * Store a newly created address for the authenticated user.
     */
    public function storeAddress(AddressRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        if (! empty($validated['is_default'])) {
            Address::where('user_id', $user->id)->update(['is_default' => false]);
        }

        $address = $user->addresses()->create($validated);
        $address->load('area.governorate');

        return ApiResponse::success(new AddressResource($address), 'Address created successfully.', 201);
    }

    /**
     * Update an address.
     */
    public function updateAddress(AddressRequest $request, Address $address): JsonResponse
    {
        Gate::authorize('update', $address);

        $user = $request->user();
        $validated = $request->validated();

        if (! empty($validated['is_default'])) {
            Address::where('user_id', $user->id)->update(['is_default' => false]);
        }

        $address->update($validated);
        $address->load('area.governorate');

        return ApiResponse::success(new AddressResource($address), 'Address updated successfully.');
    }

    /**
     * Delete an address.
     */
    public function destroyAddress(Request $request, Address $address): JsonResponse
    {
        Gate::authorize('delete', $address);

        $address->delete();

        return ApiResponse::success(null, 'Address deleted successfully.');
    }
}
