<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\Governorate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class AddressController extends Controller
{
    /**
     * Store a new address
     */
    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'area_id' => 'required|integer|exists:areas,id',
            'phone_number' => 'required|string|max:20',
            'street' => 'required|string|max:255',
            'building' => 'required|string|max:255',
            'floor' => 'required|string|max:50',
            'apartment' => 'required|string|max:50',
            'notes' => 'nullable|string|max:1000',
            'is_default' => 'nullable|boolean',
        ]);

        $user = Auth::user();

        if (!$user) {
            return back()->withErrors('يجب تسجيل الدخول لإضافة عنوان.');
        }

        // If this is set as default, unset other defaults
        if ($validated['is_default'] ?? false) {
            Address::where('user_id', $user->id)->update(['is_default' => false]);
        }

        $address = $user->addresses()->create($validated);

        // Load relationships
        $address->load('area.governorate');

        return back()->with('success', 'تم إضافة العنوان بنجاح.');
    }

    /**
     * Update an existing address
     */
    public function update(Request $request, Address $address): \Illuminate\Http\RedirectResponse
    {
        if ($address->user_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'area_id' => 'required|integer|exists:areas,id',
            'phone_number' => 'required|string|max:20',
            'street' => 'required|string|max:255',
            'building' => 'required|string|max:255',
            'floor' => 'required|string|max:50',
            'apartment' => 'required|string|max:50',
            'notes' => 'nullable|string|max:1000',
            'is_default' => 'nullable|boolean',
        ]);

        // If this is set as default, unset other defaults
        if ($validated['is_default'] ?? false) {
            Address::where('user_id', Auth::id())->update(['is_default' => false]);
        }

        $address->update($validated);

        return back()->with('success', 'تم تحديث العنوان بنجاح.');
    }

    /**
     * Delete an address
     */
    public function destroy(Address $address): \Illuminate\Http\RedirectResponse
    {
        if ($address->user_id !== Auth::id()) {
            abort(403);
        }

        $address->delete();

        return back()->with('success', 'تم حذف العنوان بنجاح.');
    }

    /**
     * Get governorates with their areas
     */
    public function getGovernoratesAreas(): JsonResponse
    {
        $governorates = Governorate::with([
            'areas' => function ($query) {
                $query->where('is_active', true)->orderBy('sort_order');
            }
        ])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'governorates' => $governorates,
        ]);
    }
}
