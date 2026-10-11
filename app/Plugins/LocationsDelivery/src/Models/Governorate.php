<?php

declare(strict_types=1);

namespace App\Plugins\LocationsDelivery\src\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Governorate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'name_ar',
        'is_active',
        'sort_order',
    ];

    public function areas(): HasMany
    {
        return $this->hasMany(Area::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
