<?php

declare(strict_types=1);

namespace App\Plugins\LocationsDelivery\src\Models;

use App\Models\User;
use App\Plugins\OrderManagement\src\Models\Order;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Address extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\AddressFactory::new();
    }

    protected $fillable = [
        'user_id',
        'area_id',
        'phone_number',
        'street',
        'building',
        'floor',
        'apartment',
        'full_address',
        'notes',
        'is_default',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }
}
