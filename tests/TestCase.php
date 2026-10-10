<?php

namespace Tests;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected static ?Tenant $testTenant = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureTenantExists();
        $this->initializeTenancy();

        $this->actingAs(User::factory()->create([
            'name' => config('app.default_user.name'),
            'email' => config('app.default_user.email'),
            'password' => config('app.default_user.password'),
        ]));

        $this->withoutVite();
    }

    /**
     * Create physical tenant and database once across the entire test suite.
     */
    protected function ensureTenantExists(): void
    {
        if (static::$testTenant !== null) {
            return;
        }

        static::$testTenant = Tenant::withoutEvents(fn (): Tenant => Tenant::create([
            'id' => 'test_suite_tenant',
        ]));

        DB::purge('tenant');
        static::$testTenant->database()->manager()->createDatabase(static::$testTenant);
    }

    /**
     * Set up tenant context and refresh schema for each individual test.
     */
    protected function initializeTenancy(): void
    {
        tenancy()->initialize(static::$testTenant);

        Artisan::call('migrate:fresh', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--realpath' => true,
            '--force' => true,
        ]);
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        DB::purge('tenant');

        parent::tearDown();
    }
}
