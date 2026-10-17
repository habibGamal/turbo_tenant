<?php

declare(strict_types=1);

namespace App\Plugins\ComboPackages\src\Models;

use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageItem extends Model
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
        $productClass = class_exists(\App\Plugins\CatalogProducts\src\Models\Product::class)
            ? \App\Plugins\CatalogProducts\src\Models\Product::class
            : Product::class;

        return $this->belongsTo($productClass);
    }

    public function variant(): BelongsTo
    {
        $variantClass = class_exists(\App\Plugins\CatalogProducts\src\Models\ProductVariant::class)
            ? \App\Plugins\CatalogProducts\src\Models\ProductVariant::class
            : ProductVariant::class;

        return $this->belongsTo($variantClass);
    }

    public function hasVariant(): bool
    {
        return $this->variant_id !== null;
    }

    public function getDisplayName(): string
    {
        $name = $this->product?->name ?? '';

        if ($this->hasVariant() && $this->variant) {
            $name .= ' - '.$this->variant->name;
        }

        return $name;
    }

    public function getEffectivePrice(): float
    {
        $basePrice = $this->hasVariant() && $this->variant
            ? (float) $this->variant->price
            : (float) ($this->product?->base_price ?? 0);

        return $basePrice + (float) $this->price_adjustment;
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
