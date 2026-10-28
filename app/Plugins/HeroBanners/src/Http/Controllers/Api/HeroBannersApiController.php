<?php

declare(strict_types=1);

namespace App\Plugins\HeroBanners\src\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Plugins\HeroBanners\src\Services\HeroBannerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class HeroBannersApiController extends Controller
{
    public function __construct(
        private readonly HeroBannerService $bannerService
    ) {}

    /**
     * Display a listing of active hero sliders/banners.
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $this->bannerService->getActiveSliders(),
        ]);
    }
}
