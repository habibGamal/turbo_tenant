<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SettingKey;
use Illuminate\Database\Eloquent\Model;

final class Setting extends Model
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
