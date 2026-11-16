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
        'pos_api_key',
        'is_active',
    ];

    /**
     * pos_api_key is added by the POS integration plugin's migration.
     */
    protected $hidden = [
        'pos_api_key',
    ];

    public function areas(): HasMany
    {
        return $this->hasMany(Area::class);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'pos_api_key' => 'encrypted',
        ];
    }
}
