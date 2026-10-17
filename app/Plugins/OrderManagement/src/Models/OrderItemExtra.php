<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Models;

use App\Plugins\CatalogProducts\src\Models\ExtraOptionItem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItemExtra extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\OrderItemExtraFactory::new();
    }

    protected $fillable = [
        'order_item_id',
        'extra_option_item_id',
        'extra_name',
        'extra_name_ar',
        'extra_price',
        'quantity',
    ];

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function extraOptionItem(): BelongsTo
    {
        return $this->belongsTo(ExtraOptionItem::class);
    }

    protected function casts(): array
    {
        return [
            'extra_price' => 'double',
            'quantity' => 'integer',
        ];
    }
}
