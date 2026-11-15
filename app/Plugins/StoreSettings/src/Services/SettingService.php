<?php

declare(strict_types=1);

namespace App\Plugins\StoreSettings\src\Services;

use App\Core\Contracts\SettingDefinition;
use App\Core\Contracts\SettingsRepository;
use App\Core\Contracts\SettingType;
use App\Enums\SettingKey;
use App\Plugins\StoreSettings\src\Models\Setting;
use BackedEnum;
use Illuminate\Support\Collection;

final class SettingService implements SettingsRepository
{
    /**
     * In-memory per-request cache of all settings for the active tenant.
     *
     * @var array<string, mixed>|null
     */
    private ?array $cache = null;

    public function get(BackedEnum|string $key, mixed $default = null): mixed
    {
        $this->ensureCacheLoaded();

        $keyValue = $this->resolveKeyString($key);
        $hasKey = array_key_exists($keyValue, $this->cache ?? []);
        $raw = $hasKey ? $this->cache[$keyValue] : null;

        if ($key instanceof SettingDefinition) {
            $fallback = $default ?? $key->defaultValue();

            if ($raw === null) {
                return $fallback;
            }

            return $this->castValue((string) $raw, $key->type(), $fallback);
        }

        return $raw ?? $default;
    }

    public function set(BackedEnum|string $key, mixed $value): void
    {
        $keyValue = $this->resolveKeyString($key);

        $serializedValue = match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            is_array($value) => json_encode($value),
            $value === null => null,
            default => (string) $value,
        };

        Setting::query()->updateOrCreate(
            ['key' => $keyValue],
            ['value' => $serializedValue]
        );

        if ($this->cache !== null) {
            $this->cache[$keyValue] = $serializedValue;
        }
    }

    /**
     * @param  array<array-key, mixed>  $settings
     */
    public function setMultiple(array $settings): void
    {
        foreach ($settings as $key => $value) {
            $this->set((string) $key, $value);
        }
    }

    /**
     * @return Collection<string, mixed>
     */
    public function getAll(): Collection
    {
        $this->ensureCacheLoaded();

        return collect($this->cache);
    }

    /**
     * @return array<string, mixed>
     */
    public function getAllAsArray(): array
    {
        $this->ensureCacheLoaded();

        $settings = [];

        foreach (SettingKey::cases() as $key) {
            $settings[$key->value] = $this->get($key);
        }

        return $settings;
    }

    public function has(BackedEnum|string $key): bool
    {
        $this->ensureCacheLoaded();

        $keyValue = $this->resolveKeyString($key);

        return array_key_exists($keyValue, $this->cache ?? []);
    }

    public function delete(BackedEnum|string $key): bool
    {
        $keyValue = $this->resolveKeyString($key);

        $deleted = (bool) Setting::query()
            ->where('key', $keyValue)
            ->delete();

        if ($this->cache !== null) {
            unset($this->cache[$keyValue]);
        }

        return $deleted;
    }

    /**
     * Load all tenant settings into memory on first read.
     */
    private function ensureCacheLoaded(): void
    {
        if ($this->cache === null) {
            $this->cache = Setting::query()->pluck('value', 'key')->all();
        }
    }

    private function resolveKeyString(BackedEnum|string $key): string
    {
        return $key instanceof BackedEnum ? (string) $key->value : (string) $key;
    }

    /**
     * Stored values are always strings: 'true'/'false' for booleans and JSON for arrays (see set()).
     */
    private function castValue(string $value, SettingType $type, mixed $fallback): mixed
    {
        return match ($type) {
            SettingType::Boolean => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            SettingType::Number => is_numeric($value)
                ? (str_contains($value, '.') ? (float) $value : (int) $value)
                : $fallback,
            SettingType::Json => is_array($decoded = json_decode($value, true)) ? $decoded : ($fallback ?? []),
            SettingType::Text => $value,
        };
    }
}
