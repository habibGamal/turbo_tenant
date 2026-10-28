<?php

declare(strict_types=1);

namespace App\Plugins\StoreSettings\src\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Keys are plain strings so plugins can store their own settings
 * (e.g. "order-management.integration_token") next to SettingKey values.
 */
final class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'key' => 'string',
        ];
    }
}
