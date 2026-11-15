<?php

declare(strict_types=1);

use App\Core\Plugins\Enums\PluginStatus;
use App\Core\Plugins\Services\PluginRegistry;
use App\Models\TenantPlugin;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    app(PluginRegistry::class)->discover();
});

it('fails when target tenant is not found', function () {
    $this->artisan('tenants:plugin:migrate', ['--tenant' => 'non_existent_tenant_xyz'])
        ->expectsOutput("Tenant 'non_existent_tenant_xyz' not found.")
        ->assertExitCode(1);
});

it('fails when target plugin is not registered on disk', function () {
    $this->artisan('tenants:plugin:migrate', ['--plugin' => 'non_existent_plugin_xyz'])
        ->expectsOutput("Plugin 'non_existent_plugin_xyz' is not registered or found on disk.")
        ->assertExitCode(1);
});

it('skips inactive plugin for a specific tenant without errors', function () {
    $tenant = static::$testTenant;

    TenantPlugin::updateOrCreate(
        ['tenant_id' => (string) $tenant->id, 'plugin_id' => 'sample-greeting'],
        ['status' => PluginStatus::DEACTIVATED]
    );

    $this->artisan('tenants:plugin:migrate', [
        '--tenant' => (string) $tenant->id,
        '--plugin' => 'sample-greeting',
    ])
        ->expectsOutputToContain("Plugin 'sample-greeting' is not active for this tenant. Skipping.")
        ->assertExitCode(0);
});

it('executes migrations for active plugins on a tenant', function () {
    $tenant = static::$testTenant;

    TenantPlugin::updateOrCreate(
        ['tenant_id' => (string) $tenant->id, 'plugin_id' => 'sample-greeting'],
        ['status' => PluginStatus::ACTIVE]
    );

    $this->artisan('tenants:plugin:migrate', [
        '--tenant' => (string) $tenant->id,
        '--plugin' => 'sample-greeting',
    ])
        ->expectsOutputToContain('Migrating plugin: sample-greeting')
        ->assertExitCode(0);

    $hasTable = $tenant->run(fn () => Schema::hasTable('sample_greetings'));
    expect($hasTable)->toBeTrue();
});

it('supports alias tenant:plugin:migrate', function () {
    $tenant = static::$testTenant;

    $this->artisan('tenant:plugin:migrate', [
        '--tenant' => (string) $tenant->id,
    ])->assertExitCode(0);
});
