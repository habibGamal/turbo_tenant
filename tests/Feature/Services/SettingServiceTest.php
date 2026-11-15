<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Core\Contracts\SettingsRepository;
use App\Enums\SettingKey;
use App\Plugins\OrderManagement\src\Settings\OrderSettingKey;
use App\Plugins\StoreSettings\src\Models\Setting;
use App\Plugins\StoreSettings\src\Services\SettingService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class SettingServiceTest extends TestCase
{
    private SettingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(SettingService::class);
    }

    public function test_setting_service_implements_settings_repository(): void
    {
        $this->assertInstanceOf(SettingsRepository::class, $this->service);
    }

    public function test_settings_repository_is_bound_to_setting_service_singleton(): void
    {
        $repo = app(SettingsRepository::class);

        $this->assertInstanceOf(SettingService::class, $repo);
        $this->assertSame($this->service, $repo);
    }

    public function test_setting_service_alias_is_removed(): void
    {
        $this->assertFalse(app()->isAlias('setting.service'));
    }

    public function test_n_get_calls_execute_only_one_sql_query(): void
    {
        Setting::query()->delete();
        Setting::query()->create(['key' => 'site_name', 'value' => 'Cached Store']);
        Setting::query()->create(['key' => 'currency', 'value' => 'USD']);

        // Fresh instance to ensure clean per-request cache
        $freshService = new SettingService();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $val1 = $freshService->get('site_name');
        $val2 = $freshService->get('currency');
        $val3 = $freshService->get('site_name');
        $hasKey = $freshService->has('site_name');
        $missing = $freshService->get('non_existent', 'default_val');

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame('Cached Store', $val1);
        $this->assertSame('USD', $val2);
        $this->assertSame('Cached Store', $val3);
        $this->assertTrue($hasKey);
        $this->assertSame('default_val', $missing);

        // Exactly one SELECT query should have run
        $this->assertCount(1, $queries, 'Expected exactly 1 query to be executed for all reads');
        $this->assertStringContainsString('settings', mb_strtolower($queries[0]['query']));
    }

    public function test_set_followed_by_get_returns_new_value(): void
    {
        $freshService = new SettingService();

        // First read populates cache
        $freshService->get('test_key');

        $freshService->set('test_key', 'updated_value');

        DB::flushQueryLog();
        DB::enableQueryLog();

        $result = $freshService->get('test_key');

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame('updated_value', $result);
        $this->assertCount(0, $queries, 'Subsequent get after set should read from in-memory cache without DB query');
    }

    public function test_get_all_as_array_uses_cache_and_executes_one_query(): void
    {
        $freshService = new SettingService();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $all = $freshService->getAllAsArray();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertArrayHasKey(SettingKey::SITE_NAME->value, $all);
        $this->assertCount(1, $queries, 'getAllAsArray should load settings with exactly 1 query, not 51');
    }

    public function test_delete_updates_cache_and_database(): void
    {
        $freshService = new SettingService();
        $freshService->set('delete_me', 'temporary');
        $this->assertTrue($freshService->has('delete_me'));

        $this->assertTrue($freshService->delete('delete_me'));
        $this->assertFalse($freshService->has('delete_me'));
        $this->assertNull($freshService->get('delete_me'));
        $this->assertDatabaseMissing('settings', ['key' => 'delete_me']);
    }

    public function test_setting_key_fallback_behaviour(): void
    {
        Setting::query()->where('key', SettingKey::SITE_NAME->value)->delete();

        $freshService = new SettingService();

        // When DB has no row, SettingKey defaults to its defaultValue()
        $this->assertSame(SettingKey::SITE_NAME->defaultValue(), $freshService->get(SettingKey::SITE_NAME));

        // When explicit default provided, it still returns defaultValue if DB has no row unless default is non-null
        $this->assertSame('Custom Default', $freshService->get('custom_key', 'Custom Default'));
    }

    public function test_setting_service_casts_boolean_number_json_and_text(): void
    {
        // 1. Boolean cast
        Setting::query()->updateOrCreate(['key' => SettingKey::MAINTENANCE_MODE->value], ['value' => 'true']);
        $this->assertTrue((new SettingService())->get(SettingKey::MAINTENANCE_MODE));

        Setting::query()->updateOrCreate(['key' => SettingKey::MAINTENANCE_MODE->value], ['value' => 'false']);
        $this->assertFalse((new SettingService())->get(SettingKey::MAINTENANCE_MODE));

        // 2. Number cast
        Setting::query()->updateOrCreate(['key' => OrderSettingKey::TAX_RATE->value], ['value' => '14.5']);
        $this->assertSame(14.5, (new SettingService())->get(OrderSettingKey::TAX_RATE));

        Setting::query()->updateOrCreate(['key' => SettingKey::MAIL_PORT->value], ['value' => '587']);
        $this->assertSame(587, (new SettingService())->get(SettingKey::MAIL_PORT));

        // 3. Json cast
        Setting::query()->updateOrCreate(['key' => SettingKey::WORK_TIMES->value], ['value' => json_encode([['day' => 'monday']])]);
        $this->assertSame([['day' => 'monday']], (new SettingService())->get(SettingKey::WORK_TIMES));

        // 4. Text cast
        Setting::query()->updateOrCreate(['key' => SettingKey::SITE_NAME->value], ['value' => 'Custom Store']);
        $this->assertSame('Custom Store', (new SettingService())->get(SettingKey::SITE_NAME));

        // 5. Plain string keeps raw value
        $this->assertSame('587', (new SettingService())->get('mail_port'));
    }

    public function test_setting_service_falls_back_to_typed_defaults(): void
    {
        Setting::query()->whereIn('key', [
            SettingKey::MAINTENANCE_MODE->value,
            OrderSettingKey::TAX_RATE->value,
            SettingKey::WORK_TIMES->value,
            SettingKey::SITE_NAME->value,
        ])->delete();

        $freshService = new SettingService();

        $this->assertFalse($freshService->get(SettingKey::MAINTENANCE_MODE));
        $this->assertSame(0.0, $freshService->get(OrderSettingKey::TAX_RATE));
        $this->assertSame([], $freshService->get(SettingKey::WORK_TIMES));
        $this->assertSame('Restaurant', $freshService->get(SettingKey::SITE_NAME));
    }

    public function test_set_serializes_boolean_to_string_and_array_to_json(): void
    {
        $freshService = new SettingService();

        // 1. Boolean serialization
        $freshService->set(SettingKey::MAINTENANCE_MODE, true);
        $this->assertDatabaseHas('settings', ['key' => SettingKey::MAINTENANCE_MODE->value, 'value' => 'true']);
        $this->assertTrue($freshService->get(SettingKey::MAINTENANCE_MODE));

        $freshService->set(SettingKey::MAINTENANCE_MODE, false);
        $this->assertDatabaseHas('settings', ['key' => SettingKey::MAINTENANCE_MODE->value, 'value' => 'false']);
        $this->assertFalse($freshService->get(SettingKey::MAINTENANCE_MODE));

        // 2. Array / Json serialization
        $freshService->set(SettingKey::WORK_TIMES, [['day' => 'sunday', 'from' => '10:00']]);
        $this->assertDatabaseHas('settings', [
            'key' => SettingKey::WORK_TIMES->value,
            'value' => json_encode([['day' => 'sunday', 'from' => '10:00']]),
        ]);
        $this->assertSame([['day' => 'sunday', 'from' => '10:00']], $freshService->get(SettingKey::WORK_TIMES));

        // 3. Plain string
        $freshService->set('plain_raw_key', 'plain_value');
        $this->assertDatabaseHas('settings', ['key' => 'plain_raw_key', 'value' => 'plain_value']);
        $this->assertSame('plain_value', $freshService->get('plain_raw_key'));
    }
}
