<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\SettingKey;
use App\Models\User;
use App\Plugins\OrderManagement\src\Settings\OrderSettingKey;
use App\Plugins\PaymentProcessing\src\Settings\PaymentSettingKey;
use App\Plugins\StoreSettings\src\Bootstrap\StoreSettingsBootstrapContributor;
use App\Plugins\StoreSettings\src\Filament\Pages\Settings;
use App\Plugins\StoreSettings\src\Models\Setting;
use App\Plugins\StoreSettings\src\Services\SettingService;
use Livewire\Livewire;
use Tests\TestCase;

final class StoreSettingsCanonicalKeysTest extends TestCase
{
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'is_admin' => true,
        ]);
        $this->actingAs($this->admin);
    }

    public function test_migration_converts_legacy_cards_and_work_times_to_canonical_keys_idempotently(): void
    {
        $settingService = app(SettingService::class);

        // 1. Seed legacy settings with old keys
        $legacyCards = [
            [
                'title' => 'Fast Delivery',
                'subtitle' => 'Delivered in 30 minutes',
                'image' => 'delivery.png',
            ],
            [
                'title' => 'Special Support',
                'description' => 'Existing description',
                'subtitle' => 'Old subtitle to discard',
                'icon' => 'support.png',
                'image' => 'old_image.png',
            ],
        ];

        $legacyWorkTimes = [
            [
                'day' => 'monday',
                'open_at' => '09:00',
                'close_at' => '17:00',
                'is_closed' => false,
            ],
            [
                'day' => 'tuesday',
                'from' => '10:00',
                'to' => '18:00',
                'closed' => false,
                'open_at' => '08:00',
                'close_at' => '16:00',
                'is_closed' => true,
            ],
            [
                'day' => 'friday',
                'open_at' => '14:00',
                'close_at' => '22:00',
                'is_closed' => 1,
            ],
        ];

        $settingService->set(SettingKey::PRODUCT_SHOW_CARDS, json_encode($legacyCards));
        $settingService->set(SettingKey::WORK_TIMES, json_encode($legacyWorkTimes));

        // 2. Run migration
        $migrationFile = base_path('app/Plugins/StoreSettings/database/migrations/tenant/2026_10_29_000001_migrate_legacy_settings_keys_to_canonical.php');
        $this->assertFileExists($migrationFile);
        $migration = require $migrationFile;
        $migration->up();

        // 3. Verify canonical keys after first run
        $migratedCards = $settingService->get(SettingKey::PRODUCT_SHOW_CARDS);
        $this->assertNotNull($migratedCards);

        $expectedCards = [
            [
                'title' => 'Fast Delivery',
                'description' => 'Delivered in 30 minutes',
                'icon' => 'delivery.png',
            ],
            [
                'title' => 'Special Support',
                'description' => 'Existing description',
                'icon' => 'support.png',
            ],
        ];
        $this->assertSame($expectedCards, $migratedCards);
        $this->assertArrayNotHasKey('subtitle', $migratedCards[0]);
        $this->assertArrayNotHasKey('image', $migratedCards[0]);
        $this->assertArrayNotHasKey('subtitle', $migratedCards[1]);
        $this->assertArrayNotHasKey('image', $migratedCards[1]);

        $migratedTimes = $settingService->get(SettingKey::WORK_TIMES);
        $this->assertNotNull($migratedTimes);

        $expectedTimes = [
            [
                'day' => 'monday',
                'from' => '09:00',
                'to' => '17:00',
                'closed' => false,
            ],
            [
                'day' => 'tuesday',
                'from' => '10:00',
                'to' => '18:00',
                'closed' => false,
            ],
            [
                'day' => 'friday',
                'from' => '14:00',
                'to' => '22:00',
                'closed' => true,
            ],
        ];
        $this->assertSame($expectedTimes, $migratedTimes);
        $this->assertTrue(is_bool($migratedTimes[0]['closed']));
        $this->assertTrue(is_bool($migratedTimes[1]['closed']));
        $this->assertTrue(is_bool($migratedTimes[2]['closed']));
        $this->assertArrayNotHasKey('open_at', $migratedTimes[0]);
        $this->assertArrayNotHasKey('close_at', $migratedTimes[0]);
        $this->assertArrayNotHasKey('is_closed', $migratedTimes[0]);

        // 4. Run migration second time (idempotence)
        $migration->up();

        $secondRunCards = $settingService->get(SettingKey::PRODUCT_SHOW_CARDS);
        $secondRunTimes = $settingService->get(SettingKey::WORK_TIMES);

        $this->assertSame($expectedCards, $secondRunCards);
        $this->assertSame($expectedTimes, $secondRunTimes);
    }

    public function test_migration_skips_invalid_or_missing_json(): void
    {
        $settingService = app(SettingService::class);

        // Delete setting row or set invalid json
        Setting::where('key', SettingKey::PRODUCT_SHOW_CARDS->value)->delete();
        $settingService->set(SettingKey::WORK_TIMES, 'invalid-json-string');

        $migrationFile = base_path('app/Plugins/StoreSettings/database/migrations/tenant/2026_10_29_000001_migrate_legacy_settings_keys_to_canonical.php');
        $this->assertFileExists($migrationFile);
        $migration = require $migrationFile;
        $migration->up();

        // Product show cards should still not exist
        $this->assertNull(Setting::where('key', SettingKey::PRODUCT_SHOW_CARDS->value)->first());

        // Work times invalid string remains untouched
        $this->assertSame('invalid-json-string', Setting::where('key', SettingKey::WORK_TIMES->value)->first()?->value);
    }

    public function test_settings_page_saves_only_canonical_keys(): void
    {
        $settingService = app(SettingService::class);
        $settingService->setMultiple([
            SettingKey::SITE_NAME->value => 'Restaurant',
            PaymentSettingKey::PAYMOB_BASE_URL->value => 'https://accept.paymob.com',
            PaymentSettingKey::PAYMOB_PUBLIC_KEY->value => 'pk_test_123',
            PaymentSettingKey::PAYMOB_SECRET_KEY->value => 'sk_test_123',
            PaymentSettingKey::PAYMOB_INTEGRATION_IDS->value => '12345',
            PaymentSettingKey::PAYMOB_HMAC_SECRET->value => 'hmac_123',
            PaymentSettingKey::PAYMOB_CURRENCY->value => 'EGP',
            PaymentSettingKey::PAYMOB_MODE->value => 'test',
            SettingKey::MAIL_MAILER->value => 'smtp',
            SettingKey::MAIL_HOST->value => 'smtp.example.com',
            SettingKey::MAIL_PORT->value => '587',
            SettingKey::MAIL_FROM_ADDRESS->value => 'test@example.com',
            SettingKey::MAIL_FROM_NAME->value => 'Restaurant',
            OrderSettingKey::ALLOW_MANUAL_ORDER_STATUS_UPDATE->value => 'false',
        ]);

        $inputCards = [
            [
                'title' => 'Fast Delivery',
                'description' => 'Delivered in 30 minutes',
                'icon' => ['delivery.png'],
            ],
        ];

        $inputWorkTimes = [
            [
                'day' => 'monday',
                'from' => '09:00:00',
                'to' => '17:00:00',
                'closed' => false,
            ],
            [
                'day' => 'friday',
                'from' => '14:00:00',
                'to' => '22:00:00',
                'closed' => true,
            ],
        ];

        Livewire::test(Settings::class)
            ->set('data.'.SettingKey::PRODUCT_SHOW_CARDS->value, $inputCards)
            ->set('data.'.SettingKey::WORK_TIMES->value, $inputWorkTimes)
            ->call('save')
            ->assertHasNoErrors();

        // Verify stored cards JSON has canonical keys only (no subtitle or image)
        $savedCards = $settingService->get(SettingKey::PRODUCT_SHOW_CARDS);
        $this->assertNotNull($savedCards);
        $this->assertIsArray($savedCards);
        $this->assertCount(1, $savedCards);
        $this->assertSame(['title' => 'Fast Delivery', 'description' => 'Delivered in 30 minutes', 'icon' => 'delivery.png'], $savedCards[0]);
        $this->assertArrayNotHasKey('subtitle', $savedCards[0]);
        $this->assertArrayNotHasKey('image', $savedCards[0]);

        // Verify stored work times JSON has canonical keys only (no open_at, close_at, is_closed)
        $savedTimes = $settingService->get(SettingKey::WORK_TIMES);
        $this->assertNotNull($savedTimes);
        $this->assertIsArray($savedTimes);
        $this->assertCount(2, $savedTimes);

        $this->assertSame(['day' => 'monday', 'from' => '09:00:00', 'to' => '17:00:00', 'closed' => false], $savedTimes[0]);
        $this->assertArrayNotHasKey('open_at', $savedTimes[0]);
        $this->assertArrayNotHasKey('close_at', $savedTimes[0]);
        $this->assertArrayNotHasKey('is_closed', $savedTimes[0]);

        $this->assertSame(['day' => 'friday', 'from' => '14:00:00', 'to' => '22:00:00', 'closed' => true], $savedTimes[1]);
        $this->assertArrayNotHasKey('open_at', $savedTimes[1]);
        $this->assertArrayNotHasKey('close_at', $savedTimes[1]);
        $this->assertArrayNotHasKey('is_closed', $savedTimes[1]);

        // Verify mounting Settings component loads canonical keys cleanly without old keys
        $reloaded = Livewire::test(Settings::class);
        $reloadedCards = array_values($reloaded->get('data.'.SettingKey::PRODUCT_SHOW_CARDS->value));
        $this->assertCount(1, $reloadedCards);
        $this->assertSame('Fast Delivery', $reloadedCards[0]['title']);
        $this->assertSame('Delivered in 30 minutes', $reloadedCards[0]['description']);
        $this->assertArrayNotHasKey('subtitle', $reloadedCards[0]);
        $this->assertArrayNotHasKey('image', $reloadedCards[0]);

        $reloadedTimes = array_values($reloaded->get('data.'.SettingKey::WORK_TIMES->value));
        $this->assertCount(2, $reloadedTimes);
        $this->assertSame('monday', $reloadedTimes[0]['day']);
        $this->assertSame('09:00:00', $reloadedTimes[0]['from']);
        $this->assertSame('17:00:00', $reloadedTimes[0]['to']);
        $this->assertFalse($reloadedTimes[0]['closed']);
        $this->assertArrayNotHasKey('open_at', $reloadedTimes[0]);
        $this->assertArrayNotHasKey('close_at', $reloadedTimes[0]);
        $this->assertArrayNotHasKey('is_closed', $reloadedTimes[0]);

        $this->assertSame('friday', $reloadedTimes[1]['day']);
        $this->assertSame('14:00:00', $reloadedTimes[1]['from']);
        $this->assertSame('22:00:00', $reloadedTimes[1]['to']);
        $this->assertTrue($reloadedTimes[1]['closed']);
        $this->assertArrayNotHasKey('open_at', $reloadedTimes[1]);
        $this->assertArrayNotHasKey('close_at', $reloadedTimes[1]);
        $this->assertArrayNotHasKey('is_closed', $reloadedTimes[1]);
    }

    public function test_store_settings_bootstrap_contributor_returns_canonical_cards(): void
    {
        $settingService = app(SettingService::class);
        $canonicalCards = [
            [
                'title' => 'Fresh Ingredients',
                'description' => '100% organic',
                'icon' => 'organic.png',
            ],
            [
                'title' => 'Quality Guarantee',
                'description' => 'Money back guarantee',
                'icon' => 'guarantee.png',
            ],
        ];

        $settingService->set(SettingKey::PRODUCT_SHOW_CARDS, json_encode($canonicalCards));

        $contributor = new StoreSettingsBootstrapContributor($settingService);
        $payload = $contributor->contribute(null);

        $this->assertArrayHasKey('settings', $payload);
        $this->assertArrayHasKey('product_show_cards', $payload['settings']);
        $this->assertSame($canonicalCards, $payload['settings']['product_show_cards']);
    }
}
