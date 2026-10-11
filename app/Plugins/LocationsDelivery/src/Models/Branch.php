<?php

declare(strict_types=1);

namespace App\Plugins\LocationsDelivery\src\Models;

use App\Models\Order;
use App\Models\ProductPosMapping;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'link',
        'is_active',
    ];

    public function posMappings(): HasMany
    {
        return $this->hasMany(ProductPosMapping::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
