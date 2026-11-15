<?php

declare(strict_types=1);

use App\Enums\SettingKey;
use App\Plugins\StoreSettings\src\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $this->migrateProductShowCards();
        $this->migrateWorkTimes();
    }

    public function down(): void
    {
        // No-op: Canonical settings keys are backward-compatible and down-migration is irreversible without data loss.
    }

    private function migrateProductShowCards(): void
    {
        $setting = Setting::query()->where('key', SettingKey::PRODUCT_SHOW_CARDS->value)->first();
        if ($setting === null || ! is_string($setting->value)) {
            return;
        }

        $decoded = json_decode($setting->value, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            return;
        }

        $migrated = [];
        foreach ($decoded as $card) {
            if (! is_array($card)) {
                $migrated[] = $card;

                continue;
            }

            if (! array_key_exists('description', $card) && array_key_exists('subtitle', $card)) {
                $card['description'] = $card['subtitle'];
            }

            if (! array_key_exists('icon', $card) && array_key_exists('image', $card)) {
                $card['icon'] = $card['image'];
            }

            unset($card['subtitle'], $card['image']);
            $migrated[] = $card;
        }

        $setting->update([
            'value' => json_encode(array_values($migrated)),
        ]);
    }

    private function migrateWorkTimes(): void
    {
        $setting = Setting::query()->where('key', SettingKey::WORK_TIMES->value)->first();
        if ($setting === null || ! is_string($setting->value)) {
            return;
        }

        $decoded = json_decode($setting->value, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            return;
        }

        $migrated = [];
        foreach ($decoded as $time) {
            if (! is_array($time)) {
                $migrated[] = $time;

                continue;
            }

            if (! array_key_exists('from', $time) && array_key_exists('open_at', $time)) {
                $time['from'] = $time['open_at'];
            }

            if (! array_key_exists('to', $time) && array_key_exists('close_at', $time)) {
                $time['to'] = $time['close_at'];
            }

            if (! array_key_exists('closed', $time) && array_key_exists('is_closed', $time)) {
                $time['closed'] = $time['is_closed'];
            }

            if (array_key_exists('closed', $time)) {
                $time['closed'] = (bool) (is_string($time['closed'])
                    ? filter_var($time['closed'], FILTER_VALIDATE_BOOLEAN)
                    : $time['closed']);
            }

            unset($time['open_at'], $time['close_at'], $time['is_closed']);
            $migrated[] = $time;
        }

        $setting->update([
            'value' => json_encode(array_values($migrated)),
        ]);
    }
};
