<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PackageItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'package_group_id',
        'product_id',
        'variant_id',
        'quantity',
        'price_adjustment',
        'is_default',
        'sort_order',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(PackageGroup::class, 'package_group_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function hasVariant(): bool
    {
        return $this->variant_id !== null;
    }

    public function getDisplayName(): string
    {
        $name = $this->product->name;

        if ($this->hasVariant() && $this->variant) {
            $name .= ' - '.$this->variant->name;
        }

        return $name;
    }

    public function getEffectivePrice(): float
    {
        $basePrice = $this->hasVariant() && $this->variant
            ? $this->variant->price
            : $this->product->base_price;

        return $basePrice + $this->price_adjustment;
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'price_adjustment' => 'double',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
