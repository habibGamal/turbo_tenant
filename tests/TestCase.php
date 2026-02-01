<?php

namespace Tests;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Initialize tenant for testing
        $this->initializeTenancy();

        $this->actingAs(User::factory()->create([
            'name' => config('app.default_user.name'),
            'email' => config('app.default_user.email'),
            'password' => config('app.default_user.password'),
        ]));

        $this->withoutVite();
    }

    protected function initializeTenancy(): void
    {
        // Create a test tenant if not exists
        $tenant = Tenant::create(['id' => 'test']);

        // Initialize tenancy context
        tenancy()->initialize($tenant);

        // Run tenant migrations
        Artisan::call('migrate', [
            '--path' => 'database/migrations/tenant',
            '--realpath' => true,
            '--force' => true,
        ]);
    }

    protected function tearDown(): void
    {
        // End tenancy context
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }
}
