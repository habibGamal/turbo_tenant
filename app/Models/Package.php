<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class Package extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'name_ar',
        'description',
        'description_ar',
        'price',
        'original_price',
        'discount_percentage',
        'badge',
        'badge_ar',
        'icon',
        'gradient',
        'is_active',
        'is_featured',
        'sort_order',
        'valid_from',
        'valid_until',
    ];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'package_product')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    protected function casts(): array
    {
        return [
            'price' => 'double',
            'original_price' => 'double',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
        ];
    }
}
