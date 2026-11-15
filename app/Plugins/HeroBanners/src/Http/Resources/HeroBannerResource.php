<?php

declare(strict_types=1);

namespace App\Plugins\HeroBanners\src\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $title
 * @property string|null $title_ar
 * @property string $subtitle
 * @property string|null $subtitle_ar
 * @property string|null $badge
 * @property string|null $badge_ar
 * @property string $cta_text
 * @property string|null $cta_text_ar
 * @property string $cta_link
 * @property string|null $secondary_cta_text
 * @property string|null $secondary_cta_text_ar
 * @property string|null $secondary_cta_link
 * @property string|null $image
 * @property string|null $gradient
 * @property string|null $icon
 * @property int $sort_order
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class HeroBannerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'title_ar' => $this->title_ar,
            'subtitle' => $this->subtitle,
            'subtitle_ar' => $this->subtitle_ar,
            'badge' => $this->badge,
            'badge_ar' => $this->badge_ar,
            'cta_text' => $this->cta_text,
            'cta_text_ar' => $this->cta_text_ar,
            'cta_link' => $this->cta_link,
            'secondary_cta_text' => $this->secondary_cta_text,
            'secondary_cta_text_ar' => $this->secondary_cta_text_ar,
            'secondary_cta_link' => $this->secondary_cta_link,
            'image' => $this->image,
            'gradient' => $this->gradient,
            'icon' => $this->icon,
            'sort_order' => (int) $this->sort_order,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toISOString() ?? (string) $this->created_at,
            'updated_at' => $this->updated_at?->toISOString() ?? (string) $this->updated_at,
        ];
    }
}
