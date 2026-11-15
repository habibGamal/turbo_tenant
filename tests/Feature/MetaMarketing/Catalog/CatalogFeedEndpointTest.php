<?php

declare(strict_types=1);

use App\Core\Contracts\SettingsRepository;
use App\Enums\SettingKey;
use App\Plugins\MetaMarketing\src\DTOs\MetaMarketingConfig;
use App\Plugins\MetaMarketing\src\Settings\MetaSettingKey;

beforeEach(function () {
    $settings = app(SettingsRepository::class);
    $settings->set(MetaSettingKey::CATALOG_ENABLED, true);
    $settings->set(MetaSettingKey::CATALOG_FEED_TOKEN, 'validtoken123');
    $settings->set(MetaSettingKey::CATALOG_BRAND, 'TestBrand');
    $settings->set(SettingKey::CURRENCY, 'EGP');
    app()->forgetInstance(MetaMarketingConfig::class);
});

test('returns 404 if token does not match config', function () {
    $response = $this->get('/meta/catalog/wrongtoken.csv');

    $response->assertNotFound();
});

test('returns 404 if catalog is disabled in settings', function () {
    $settings = app(SettingsRepository::class);
    $settings->set(MetaSettingKey::CATALOG_ENABLED, false);
    app()->forgetInstance(MetaMarketingConfig::class);

    $response = $this->get('/meta/catalog/validtoken123.csv');

    $response->assertNotFound();
});

test('returns 404 if feed token is not configured', function () {
    $settings = app(SettingsRepository::class);
    $settings->set(MetaSettingKey::CATALOG_FEED_TOKEN, '');
    app()->forgetInstance(MetaMarketingConfig::class);

    $response = $this->get('/meta/catalog/validtoken123.csv');

    $response->assertNotFound();
});

test('returns 200 with text/csv content type for csv feed', function () {
    $response = $this->get('/meta/catalog/validtoken123.csv');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('text/csv; charset=UTF-8');
});

test('returns 200 with application/xml content type for xml feed', function () {
    $response = $this->get('/meta/catalog/validtoken123.xml');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('application/xml; charset=UTF-8');
});

test('arabic override returns 404 for wrong token', function () {
    $this->get('/meta/catalog/wrongtoken.ar.csv')->assertNotFound();
});

test('arabic override returns 404 for disabled catalog', function () {
    $settings = app(SettingsRepository::class);
    $settings->set(MetaSettingKey::CATALOG_ENABLED, false);
    app()->forgetInstance(MetaMarketingConfig::class);

    $this->get('/meta/catalog/validtoken123.ar.csv')->assertNotFound();
});

test('arabic override returns 200 with text/csv content type', function () {
    $response = $this->get('/meta/catalog/validtoken123.ar.csv');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('text/csv; charset=UTF-8');
});
