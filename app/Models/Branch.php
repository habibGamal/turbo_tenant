<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Branch extends Model
{
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
