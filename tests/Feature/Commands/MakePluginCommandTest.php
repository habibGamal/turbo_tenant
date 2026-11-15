<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class MakePluginCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        $testPluginDir = app_path('Plugins/TestScaffoldTemp');
        if (File::isDirectory($testPluginDir)) {
            File::deleteDirectory($testPluginDir);
        }

        parent::tearDown();
    }

    public function test_make_plugin_scaffolds_plugin_class_without_empty_lifecycle_overrides(): void
    {
        $testPluginDir = app_path('Plugins/TestScaffoldTemp');
        if (File::isDirectory($testPluginDir)) {
            File::deleteDirectory($testPluginDir);
        }

        $this->artisan('make:plugin', [
            'slug' => 'test-scaffold-temp',
            '--name' => 'Test Scaffold Temp',
        ])->assertSuccessful();

        $pluginClassPath = "{$testPluginDir}/TestScaffoldTempPlugin.php";
        $this->assertFileExists($pluginClassPath);

        $contents = File::get($pluginClassPath);

        $this->assertStringContainsString('public function bootForTenant(Tenant $tenant): void', $contents);
        $this->assertStringNotContainsString('public function onActivate', $contents);
        $this->assertStringNotContainsString('public function onDeactivate', $contents);
        $this->assertStringNotContainsString('public function onUninstall', $contents);

        $manifestPath = "{$testPluginDir}/plugin.json";
        $this->assertFileExists($manifestPath);
        $manifest = json_decode(File::get($manifestPath), true);
        $this->assertSame('App\\Plugins\\TestScaffoldTemp\\TestScaffoldTempPlugin', $manifest['plugin'] ?? null);
    }
}
