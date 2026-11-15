<?php

declare(strict_types=1);

use App\Core\Contracts\SettingsRepository;
use App\Plugins\MetaMarketing\src\DTOs\MetaMarketingConfig;
use App\Plugins\MetaMarketing\src\Settings\MetaSettingKey;
use App\Plugins\MetaMarketing\src\Support\MetaContentId;
use Illuminate\Support\Facades\Crypt;

test('content ids use the product id, or product-variant for variants', function () {
    expect(MetaContentId::for(12))->toBe('12')
        ->and(MetaContentId::for(12, 7))->toBe('12-7')
        ->and(MetaContentId::group(12))->toBe('12');
});

test('config decrypts the stored access token and only allows server events when fully configured', function () {
    $settings = app(SettingsRepository::class);
    $settings->set(MetaSettingKey::PIXEL_ID, '123456789012345');
    $settings->set(MetaSettingKey::CAPI_ENABLED, true);
    $settings->set(MetaSettingKey::CAPI_ACCESS_TOKEN, Crypt::encryptString('secret-token'));
    app()->forgetInstance(MetaMarketingConfig::class);

    $config = app(MetaMarketingConfig::class);

    expect($config->accessToken)->toBe('secret-token')
        ->and($config->canSendServerEvents())->toBeTrue();
});

test('a token that is not encrypted is ignored instead of being used as plain text', function () {
    $settings = app(SettingsRepository::class);
    $settings->set(MetaSettingKey::PIXEL_ID, '123456789012345');
    $settings->set(MetaSettingKey::CAPI_ENABLED, true);
    $settings->set(MetaSettingKey::CAPI_ACCESS_TOKEN, 'plain-token');
    app()->forgetInstance(MetaMarketingConfig::class);

    $config = app(MetaMarketingConfig::class);

    expect($config->accessToken)->toBeNull()
        ->and($config->canSendServerEvents())->toBeFalse();
});

test('bootstrap exposes the pixel id but never the access token', function () {
    $settings = app(SettingsRepository::class);
    $settings->set(MetaSettingKey::PIXEL_ID, '123456789012345');
    $settings->set(MetaSettingKey::CAPI_ACCESS_TOKEN, Crypt::encryptString('secret-token'));
    app()->forgetInstance(MetaMarketingConfig::class);

    $response = $this->getJson('/api/tenant/bootstrap');

    $response->assertOk();
    expect($response->json('data.settings.meta_pixel_id') ?? $response->json('settings.meta_pixel_id'))->toBe('123456789012345')
        ->and($response->getContent())->not->toContain('secret-token')
        ->and($response->getContent())->not->toContain('meta_capi_access_token');
});

test('the plugin does not use the Laravel cache (tenant cache needs a taggable store; the app runs on the database store)', function () {
    $offenders = collect(Illuminate\Support\Facades\File::allFiles(app_path('Plugins/MetaMarketing')))
        ->filter(fn ($file) => $file->getExtension() === 'php')
        ->filter(fn ($file) => preg_match('/\bCache::|\bcache\(\)|Facades\\Cache\b/', $file->getContents()) === 1)
        ->map(fn ($file) => $file->getRelativePathname())
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});
