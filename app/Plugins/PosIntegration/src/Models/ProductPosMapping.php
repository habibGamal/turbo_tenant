<?php

declare(strict_types=1);

namespace App\Plugins\PosIntegration\src\Models;

use App\Plugins\CatalogProducts\src\Models\ExtraOptionItem;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Models\ProductVariant;
use App\Plugins\LocationsDelivery\src\Models\Branch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProductPosMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'variant_id',
        'extra_option_item_id',
        'branch_id',
        'pos_item_id',
    ];

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /**
     * @return BelongsTo<ExtraOptionItem, $this>
     */
    public function extraOptionItem(): BelongsTo
    {
        return $this->belongsTo(ExtraOptionItem::class, 'extra_option_item_id');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    protected static function newFactory()
    {
        return \Database\Factories\ProductPosMappingFactory::new();
    }
}
