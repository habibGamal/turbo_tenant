<?php

declare(strict_types=1);

namespace App\Plugins\StoreSettings\src\Services;

use App\Enums\SettingKey;
use App\Plugins\StoreSettings\src\Models\Setting;
use Illuminate\Support\Collection;

class SettingService
{
    public function get(SettingKey|string $key, mixed $default = null): mixed
    {
        $keyValue = $key instanceof SettingKey ? $key->value : $key;

        $setting = Setting::query()
            ->where('key', $keyValue)
            ->first();

        $fallback = $key instanceof SettingKey ? $key->defaultValue() : null;

        return $setting?->value ?? $default ?? $fallback;
    }

    public function set(SettingKey|string $key, mixed $value): void
    {
        $keyValue = $key instanceof SettingKey ? $key->value : $key;

        Setting::query()->updateOrCreate(
            ['key' => $keyValue],
            ['value' => $value]
        );
    }

    public function setMultiple(array $settings): void
    {
        foreach ($settings as $key => $value) {
            $this->set($key, $value);
        }
    }

    public function getAll(): Collection
    {
        return Setting::query()->pluck('value', 'key');
    }

    public function getAllAsArray(): array
    {
        $settings = [];

        foreach (SettingKey::cases() as $key) {
            $settings[$key->value] = $this->get($key);
        }

        return $settings;
    }

    public function has(SettingKey|string $key): bool
    {
        $keyValue = $key instanceof SettingKey ? $key->value : $key;

        return Setting::query()
            ->where('key', $keyValue)
            ->exists();
    }

    public function delete(SettingKey|string $key): bool
    {
        $keyValue = $key instanceof SettingKey ? $key->value : $key;

        return (bool) Setting::query()
            ->where('key', $keyValue)
            ->delete();
    }
}
