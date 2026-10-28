<?php

declare(strict_types=1);

namespace Tests;

use App\Models\User;

abstract class CentralTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->useCentralConnection();

        // Create central admin user
        $this->actingAs(User::factory()->create([
            'name' => 'Central Admin',
            'email' => 'central@admin.com',
            'password' => 'password',
            'is_admin' => true,
        ]));

        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        $this->useCentralConnection();

        parent::tearDown();
    }

    protected function shouldInitializeTenancy(): bool
    {
        return false;
    }
}
