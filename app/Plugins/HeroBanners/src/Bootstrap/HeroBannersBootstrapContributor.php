<?php

declare(strict_types=1);

namespace App\Plugins\HeroBanners\src\Bootstrap;

use App\Core\Plugins\Contracts\BootstrapContributor;
use App\Plugins\HeroBanners\src\Http\Resources\HeroBannerResource;
use App\Plugins\HeroBanners\src\Services\HeroBannerService;
use Illuminate\Contracts\Auth\Authenticatable;

final class HeroBannersBootstrapContributor implements BootstrapContributor
{
    public function __construct(private readonly HeroBannerService $banners) {}

    public function pluginId(): string
    {
        return 'hero-banners';
    }

    public function contribute(?Authenticatable $user): array
    {
        return ['query_data' => [[
            'key' => ['hero-banners', null],
            'data' => HeroBannerResource::collection($this->banners->getActiveSliders())->resolve(),
        ]]];
    }
}
