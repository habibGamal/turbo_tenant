<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ProductVariant extends Model
{
    protected $fillable = [
        'product_id',
        'name',
        'price',
        'is_available',
        'sort_order',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function posMappings(): HasMany
    {
        return $this->hasMany(ProductPosMapping::class, 'variant_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'variant_id');
    }

    protected function casts(): array
    {
        return [
            'price' => 'double',
            'is_available' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
