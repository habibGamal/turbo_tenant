<?php

declare(strict_types=1);

namespace App\Plugins\ComboPackages\src\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Plugins\ComboPackages\src\Models\Package;
use Illuminate\Http\JsonResponse;

class PackageController extends Controller
{
    public function index(): JsonResponse
    {
        $packages = Package::query()
            ->where('is_active', true)
            ->with([
                'groups' => fn ($query) => $query->orderBy('sort_order'),
                'groups.items' => fn ($query) => $query->orderBy('sort_order'),
                'groups.items.product',
                'groups.items.variant',
            ])
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $packages,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $package = Package::query()
            ->with([
                'groups' => fn ($query) => $query->orderBy('sort_order'),
                'groups.items' => fn ($query) => $query->orderBy('sort_order'),
                'groups.items.product',
                'groups.items.variant',
            ])
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $package,
        ]);
    }
}
