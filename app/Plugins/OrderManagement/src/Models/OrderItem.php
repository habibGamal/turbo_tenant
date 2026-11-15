<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Models;

use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Models\ProductVariant;
use App\Plugins\CatalogProducts\src\Models\WeightOptionValue;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'line_type',
        'line_reference_id',
        'product_id',
        'variant_id',
        'weight_option_value_id',
        'weight_multiplier',
        'product_name',
        'product_name_ar',
        'variant_name',
        'variant_name_ar',
        'notes',
        'weight_label',
        'extras_unit_total',
        'quantity',
        'unit_price',
        'total',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function weightOptionValue(): BelongsTo
    {
        return $this->belongsTo(WeightOptionValue::class, 'weight_option_value_id');
    }

    public function extras(): HasMany
    {
        return $this->hasMany(OrderItemExtra::class);
    }

    public function components(): HasMany
    {
        return $this->hasMany(OrderItemComponent::class)->orderBy('sort_order');
    }

    public function isProductLine(): bool
    {
        return ($this->line_type ?? 'product') === 'product';
    }

    protected static function newFactory()
    {
        return \Database\Factories\OrderItemFactory::new();
    }

    protected function casts(): array
    {
        return [
            'line_reference_id' => 'integer',
            'product_id' => 'integer',
            'quantity' => 'decimal:3',
            'unit_price' => 'float',
            'total' => 'float',
            'extras_unit_total' => 'float',
            'weight_multiplier' => 'integer',
        ];
    }
}
