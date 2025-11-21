<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SettingKey;
use App\Models\Setting;
use Illuminate\Support\Collection;

final class SettingService
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

    public function seedDefaults(): void
    {
        foreach (SettingKey::cases() as $key) {
            if ($key->defaultValue() !== null) {
                Setting::query()->firstOrCreate(
                    ['key' => $key->value],
                    ['value' => $key->defaultValue()]
                );
            }
        }
    }
}
