<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Coupon extends Model
{
    protected $fillable = [
        'code',
        'type',
        'value',
        'expiry_date',
        'is_active',
        'max_usage',
        'usage_count',
        'total_consumed',
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
        ];
    }
}
