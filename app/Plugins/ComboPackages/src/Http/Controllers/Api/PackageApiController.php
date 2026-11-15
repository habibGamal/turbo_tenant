<?php

declare(strict_types=1);

namespace App\Plugins\ComboPackages\src\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Plugins\ComboPackages\src\Http\Resources\PackageResource;
use App\Plugins\ComboPackages\src\Models\Package;
use App\Plugins\ComboPackages\src\Services\SellablePackageFilter;
use App\Plugins\ComboPackages\src\Services\StorefrontData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PackageApiController extends Controller
{
    public function __construct(
        private readonly StorefrontData $storefrontData,
        private readonly SellablePackageFilter $sellablePackageFilter,
    ) {}

    /**
     * Display a listing of active combo packages.
     */
    public function index(Request $request): JsonResponse
    {
        $productId = $request->query('product_id');
        $productIdInt = (is_numeric($productId) && (int) $productId > 0) ? (int) $productId : null;

        return ApiResponse::success($this->storefrontData->index($productIdInt));
    }

    /**
     * Display details for a specific combo package.
     */
    public function show(int $id): JsonResponse
    {
        $package = Package::query()
            ->with([
                'availabilities',
                'branches',
                'groups' => fn ($query) => $query->orderBy('sort_order'),
                'groups.items' => fn ($query) => $query->orderBy('sort_order'),
                'groups.items.product.variants',
                'groups.items.product.extraOption.items',
                'groups.items.product',
                'groups.items.variant',
            ])
            ->find($id);

        if (! $package) {
            abort(404, 'Package not found or unavailable.');
        }

        $sellablePackage = $this->sellablePackageFilter->filterOne($package);

        if (! $sellablePackage) {
            abort(404, 'Package not found or unavailable.');
        }

        return ApiResponse::success(new PackageResource($sellablePackage));
    }
}
