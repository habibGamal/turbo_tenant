<?php

declare(strict_types=1);

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class CentralTestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // End any existing tenancy context
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        // Create central admin user
        $this->actingAs(User::factory()->create([
            'name' => 'Central Admin',
            'email' => 'central@admin.com',
            'password' => 'password',
            'is_admin' => true,
        ]));

        $this->withoutVite();
    }

    protected function tearDown( ): void
    {
        // Ensure tenancy is ended
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }
}
