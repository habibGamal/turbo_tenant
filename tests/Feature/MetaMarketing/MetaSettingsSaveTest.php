<?php

declare(strict_types=1);

use App\Core\Contracts\SettingsRepository;
use App\Models\User;
use App\Plugins\MetaMarketing\src\DTOs\MetaMarketingConfig;
use App\Plugins\MetaMarketing\src\Settings\MetaSettingKey;
use App\Plugins\StoreSettings\src\Filament\Pages\Settings;
use Illuminate\Support\Facades\Crypt;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    // The settings page saves every tab at once, so other tabs' required fields must be valid.
    $settings = app(SettingsRepository::class);
    foreach ([
        'paymob_public_key' => 'pk_test', 'paymob_secret_key' => 'sk_test', 'paymob_integration_ids' => '1',
        'paymob_hmac_secret' => 'hmac_test', 'mail_from_address' => 'store@example.com', 'mail_from_name' => 'Store',
    ] as $key => $value) {
        $settings->set($key, $value);
    }
});

test('saving the access token on the settings page stores it encrypted and never echoes it back', function () {
    $tokenKey = 'data.'.MetaSettingKey::CAPI_ACCESS_TOKEN->value;

    Livewire::test(Settings::class)
        ->set('data.'.MetaSettingKey::PIXEL_ID->value, '5195611370664031')
        ->set($tokenKey, '  EAAB-real-token  ')
        ->call('save')
        ->assertHasNoErrors();

    $stored = (string) app(SettingsRepository::class)->get(MetaSettingKey::CAPI_ACCESS_TOKEN);
    expect($stored)->not->toBe('')
        ->and($stored)->not->toContain('EAAB-real-token')
        ->and(Crypt::decryptString($stored))->toBe('EAAB-real-token');

    app()->forgetInstance(MetaMarketingConfig::class);
    expect(app(MetaMarketingConfig::class)->accessToken)->toBe('EAAB-real-token');

    // The form never shows the stored secret.
    expect(Livewire::test(Settings::class)->get($tokenKey))->toBeNull();
});

test('saving the settings page with an empty token field keeps the stored token', function () {
    $settings = app(SettingsRepository::class);
    $settings->set(MetaSettingKey::CAPI_ACCESS_TOKEN, Crypt::encryptString('kept-token'));

    Livewire::test(Settings::class)
        ->set('data.'.MetaSettingKey::CAPI_TEST_EVENT_CODE->value, 'TEST123')
        ->call('save')
        ->assertHasNoErrors();

    expect(Crypt::decryptString((string) $settings->get(MetaSettingKey::CAPI_ACCESS_TOKEN)))->toBe('kept-token');
});
