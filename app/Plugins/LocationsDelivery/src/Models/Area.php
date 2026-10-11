<?php

declare(strict_types=1);

namespace App\Plugins\LocationsDelivery\src\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Area extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'name_ar',
        'shipping_cost',
        'governorate_id',
        'branch_id',
        'is_active',
        'sort_order',
    ];

    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    protected function casts(): array
    {
        return [
            'shipping_cost' => 'double',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
