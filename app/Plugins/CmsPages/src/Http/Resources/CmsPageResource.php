<?php

declare(strict_types=1);

namespace App\Plugins\CmsPages\src\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $title
 * @property string|null $title_ar
 * @property string $slug
 * @property string|null $content
 * @property string|null $content_ar
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class CmsPageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $attributes = $this->resource->getAttributes();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'title_ar' => $this->title_ar,
            'slug' => $this->slug,
            'content' => $this->when(array_key_exists('content', $attributes), fn () => $this->content),
            'content_ar' => $this->when(array_key_exists('content_ar', $attributes), fn () => $this->content_ar),
            'is_active' => $this->when(array_key_exists('is_active', $attributes), fn () => (bool) $this->is_active),
            'created_at' => $this->created_at?->toISOString() ?? (string) $this->created_at,
            'updated_at' => $this->updated_at?->toISOString() ?? (string) $this->updated_at,
        ];
    }
}
