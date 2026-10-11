<?php

declare(strict_types=1);

namespace App\Plugins\StoreSettings\src\Models;

use App\Enums\SettingKey;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'key' => SettingKey::class,
        ];
    }
}
