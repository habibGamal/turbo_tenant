<?php

declare(strict_types=1);

namespace App\Plugins\LocationsDelivery\src\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Plugins\LocationsDelivery\src\Models\Address;
use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\LocationsDelivery\src\Models\Governorate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LocationsDeliveryApiController extends Controller
{
    /**
     * Get aggregate delivery locations metadata and customer addresses if logged in.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $addresses = $user
            ? $user->addresses()->with(['area.governorate'])->latest()->get()
            : [];

        $governorates = Governorate::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with(['areas' => function ($query) {
                $query->where('is_active', true)->orderBy('sort_order');
            }])
            ->get();

        $branches = Branch::query()
            ->where('is_active', true)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'addresses' => $addresses,
                'governorates' => $governorates,
                'branches' => $branches,
            ],
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

        return response()->json([
            'status' => 'success',
            'data' => $governorates,
        ]);
    }

    /**
     * Get active branches.
     */
    public function branches(Request $request): JsonResponse
    {
        $branches = Branch::query()
            ->where('is_active', true)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $branches,
        ]);
    }

    /**
     * Get user addresses.
     */
    public function addresses(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $addresses = $user->addresses()
            ->with(['area.governorate'])
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $addresses,
        ]);
    }

    /**
     * Store a newly created address for the authenticated user.
     */
    public function storeAddress(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $validated = $request->validate([
            'area_id' => 'required|integer|exists:areas,id',
            'phone_number' => [
                'required',
                'string',
                'regex:/^\+20[0-9]{10}$/',
            ],
            'street' => 'required|string|max:255',
            'building' => 'required|string|max:255',
            'floor' => 'required|string|max:50',
            'apartment' => 'required|string|max:50',
            'notes' => 'nullable|string|max:1000',
            'is_default' => 'nullable|boolean',
        ], [
            'phone_number.regex' => 'رقم الهاتف يجب أن يكون رقم مصري صحيح.',
        ]);

        if (! empty($validated['is_default'])) {
            Address::where('user_id', $user->id)->update(['is_default' => false]);
        }

        $address = $user->addresses()->create($validated);
        $address->load('area.governorate');

        return response()->json([
            'status' => 'success',
            'message' => 'Address created successfully.',
            'data' => $address,
        ], 201);
    }

    /**
     * Update an address.
     */
    public function updateAddress(Request $request, Address $address): JsonResponse
    {
        $user = $request->user();

        if (! $user || $address->user_id !== $user->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized.',
            ], 403);
        }

        $validated = $request->validate([
            'area_id' => 'required|integer|exists:areas,id',
            'phone_number' => [
                'required',
                'string',
                'regex:/^\+20[0-9]{10}$/',
            ],
            'street' => 'required|string|max:255',
            'building' => 'required|string|max:255',
            'floor' => 'required|string|max:50',
            'apartment' => 'required|string|max:50',
            'notes' => 'nullable|string|max:1000',
            'is_default' => 'nullable|boolean',
        ], [
            'phone_number.regex' => 'رقم الهاتف يجب أن يكون رقم مصري صحيح.',
        ]);

        if (! empty($validated['is_default'])) {
            Address::where('user_id', $user->id)->update(['is_default' => false]);
        }

        $address->update($validated);
        $address->load('area.governorate');

        return response()->json([
            'status' => 'success',
            'message' => 'Address updated successfully.',
            'data' => $address,
        ]);
    }

    /**
     * Delete an address.
     */
    public function destroyAddress(Request $request, Address $address): JsonResponse
    {
        $user = $request->user();

        if (! $user || $address->user_id !== $user->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized.',
            ], 403);
        }

        $address->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Address deleted successfully.',
        ]);
    }
}
