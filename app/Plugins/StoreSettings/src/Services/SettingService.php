<?php

declare(strict_types=1);

namespace App\Plugins\StoreSettings\src\Services;

use App\Enums\SettingKey;
use App\Plugins\StoreSettings\src\Models\Setting;
use Illuminate\Support\Collection;

class SettingService
{
    public function get(SettingKey $key, mixed $default = null): mixed
    {
        $setting = Setting::query()
            ->where('key', $key->value)
            ->first();

        return $setting?->value ?? $default ?? $key->defaultValue();
    }

    public function set(SettingKey $key, mixed $value): void
    {
        Setting::query()->updateOrCreate(
            ['key' => $key->value],
            ['value' => $value]
        );
    }

    public function setMultiple(array $settings): void
    {
        foreach ($settings as $key => $value) {
            if ($key instanceof SettingKey) {
                $settingKey = $key;
            } else {
                $settingKey = SettingKey::from($key);
            }

            Setting::query()->updateOrCreate(
                ['key' => $settingKey->value],
                ['value' => $value]
            );
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

    public function has(SettingKey $key): bool
    {
        return Setting::query()
            ->where('key', $key->value)
            ->exists();
    }

    public function delete(SettingKey $key): bool
    {
        return (bool) Setting::query()
            ->where('key', $key->value)
            ->delete();
    }
}
