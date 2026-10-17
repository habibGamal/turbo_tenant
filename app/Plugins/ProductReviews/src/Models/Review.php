<?php

declare(strict_types=1);

namespace App\Plugins\ProductReviews\src\Models;

use App\Models\User;
use App\Plugins\CatalogProducts\src\Models\Product;
use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    use HasFactory;

    protected $table = 'reviews';

    protected $fillable = [
        'user_id',
        'product_id',
        'guest_name',
        'guest_email',
        'rating',
        'comment',
        'images',
        'is_published',
        'is_approved',
    ];

    protected $casts = [
        'images' => 'array',
        'is_published' => 'boolean',
        'is_approved' => 'boolean',
        'rating' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected function authorName(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->user?->name ?? $this->guest_name ?? 'Guest'
        );
    }

    protected static function newFactory(): ReviewFactory
    {
        return ReviewFactory::new();
    }
}
