<?php

declare(strict_types=1);

namespace App\Plugins\HeroBanners\src\Services;

use App\Plugins\HeroBanners\src\DTOs\HeroBannersConfig;
use App\Plugins\HeroBanners\src\Models\HeroSlider;
use Illuminate\Database\Eloquent\Collection;

final class HeroBannerService
{
    public function __construct(
        private readonly HeroBannersConfig $config
    ) {}

    /**
     * Retrieve all active hero sliders sorted by sort_order.
     *
     * @return Collection<int, HeroSlider>
     */
    public function getActiveSliders(): Collection
    {
        if (! $this->config->enabled) {
            return new Collection();
        }

        return HeroSlider::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }
}
