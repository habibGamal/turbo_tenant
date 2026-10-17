<?php

declare(strict_types=1);

namespace App\Plugins\HeroBanners\src\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Plugins\HeroBanners\src\Services\HeroBannerService;
use Illuminate\Http\JsonResponse;

class HeroSliderController extends Controller
{
    public function __construct(
        private readonly HeroBannerService $bannerService
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->bannerService->getActiveSliders(),
        ]);
    }
}
