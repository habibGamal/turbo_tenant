<?php

declare(strict_types=1);

namespace App\Plugins\LocationsDelivery\src\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'link',
        'is_active',
    ];

    public function areas(): HasMany
    {
        return $this->hasMany(Area::class);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
