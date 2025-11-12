<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Address extends Model
{
    protected $fillable = [
        'area',
        'full_address',
        'profile_id',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
