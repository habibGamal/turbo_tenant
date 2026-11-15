<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Models;

use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OrderItemComponent extends Model
{
    protected $fillable = [
        'order_item_id',
        'source_id',
        'group_name',
        'group_name_ar',
        'product_id',
        'variant_id',
        'product_name',
        'product_name_ar',
        'variant_name',
        'variant_name_ar',
        'quantity',
        'unit_price_adjustment',
        'sort_order',
        'extras',
        'notes',
    ];

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    protected function casts(): array
    {
        return [
            'source_id' => 'integer',
            'product_id' => 'integer',
            'variant_id' => 'integer',
            'quantity' => 'decimal:3',
            'unit_price_adjustment' => 'decimal:2',
            'sort_order' => 'integer',
            'extras' => 'array',
        ];
    }
}
