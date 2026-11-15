<?php

declare(strict_types=1);

namespace App\Plugins\ProductReviews\src\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property int $product_id
 * @property int|null $user_id
 * @property string|null $guest_name
 * @property string|null $guest_email
 * @property int $rating
 * @property string $comment
 * @property array<int, string>|null $images
 * @property bool $is_published
 * @property bool $is_approved
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \App\Models\User|null $user
 */
final class ReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // Public listing: never expose the reviewer's email or account id.
            'id' => $this->id,
            'product_id' => $this->product_id,
            'guest_name' => $this->guest_name,
            'user_name' => $this->user?->name ?? $this->guest_name ?? 'Anonymous',
            'rating' => (int) $this->rating,
            'comment' => $this->comment,
            'images' => $this->images ?? [],
            'is_published' => (bool) $this->is_published,
            'is_approved' => (bool) $this->is_approved,
            'created_at' => $this->created_at?->toISOString() ?? (string) $this->created_at,
            'updated_at' => $this->updated_at?->toISOString() ?? (string) $this->updated_at,
            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ] : null),
        ];
    }
}
