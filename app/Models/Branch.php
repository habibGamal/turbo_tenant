<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'link',
        'api_key',
        'is_active',
    ];

    protected $hidden = [
        'api_key',
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
            'api_key' => 'encrypted',
        ];
    }
}
