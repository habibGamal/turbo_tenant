<?php

declare(strict_types=1);

namespace App\Plugins\StoreSettings\src\Models;

use App\Enums\SettingKey;
use Illuminate\Database\Eloquent\Model;

final class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    public function getKeyAttribute(mixed $value): SettingKey|string|null
    {
        if ($value === null) {
            return null;
        }

        return SettingKey::tryFrom((string) $value) ?? (string) $value;
    }

    public function setKeyAttribute(mixed $value): void
    {
        $this->attributes['key'] = $value instanceof SettingKey ? $value->value : (string) $value;
    }
}
