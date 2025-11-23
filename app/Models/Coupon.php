<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Coupon extends Model
{
    use HasFactory;
    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'value',
        'expiry_date',
        'is_active',
        'max_usage',
        'usage_count',
        'total_consumed',
        'conditions',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    protected function casts(): array
    {
        return [
            'value' => 'double',
            'expiry_date' => 'datetime',
            'is_active' => 'boolean',
            'max_usage' => 'integer',
            'usage_count' => 'integer',
            'total_consumed' => 'double',
            'conditions' => 'array',
        ];
    }

    /**
     * Get default conditions structure
     */
    public function getDefaultConditions(): array
    {
        return [
            'min_order_total' => null,
            'max_order_total' => null,
            'applicable_to' => [
                'type' => 'all', // all, products, categories
                'product_ids' => [],
                'category_ids' => [],
            ],
            'shipping' => [
                'free_shipping' => false,
                'free_shipping_threshold' => null,
                'applicable_governorates' => [],
                'applicable_areas' => [],
            ],
            'usage_restrictions' => [
                'first_order_only' => false,
                'user_specific' => false,
                'user_ids' => [],
            ],
            'valid_days' => null, // null means all days, or [0,1,2,3,4,5,6] for specific days
            'valid_hours' => [
                'start' => null,
                'end' => null,
            ],
        ];
    }

    /**
     * Get merged conditions with defaults
     */
    public function getConditionsAttribute($value): array
    {
        $conditions = $value ? json_decode($value, true) : [];
        return array_replace_recursive($this->getDefaultConditions(), $conditions);
    }
}
