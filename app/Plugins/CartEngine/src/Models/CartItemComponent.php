<?php

declare(strict_types=1);

namespace App\Plugins\CartEngine\src\Models;

use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A product picked inside a composite cart line. Only the selection is stored;
 * names and prices are rebuilt by the line's CartLineTypeHandler on every read.
 */
final class CartItemComponent extends Model
{
    protected $fillable = [
        'cart_item_id',
        'source_id',
        'product_id',
        'variant_id',
        'quantity',
        'unit_price_adjustment',
        'extras',
        'notes',
        'sort_order',
    ];

    public function cartItem(): BelongsTo
    {
        return $this->belongsTo(CartItem::class);
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
            'quantity' => 'decimal:3',
            'unit_price_adjustment' => 'decimal:2',
            'extras' => 'array',
            'sort_order' => 'integer',
        ];
    }
}
