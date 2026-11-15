<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Contracts\Customer;
use App\Core\Contracts\SettingsRepository;
use App\Models\User;
use ReflectionClass;
use Tests\TestCase;

final class CustomerContractTest extends TestCase
{
    public function test_user_implements_customer_contract(): void
    {
        $user = new User([
            'name' => 'Alice Doe',
            'email' => 'alice@example.com',
        ]);
        $user->id = 123;
        $user->setAttribute('phone', '+201234567890');

        $this->assertInstanceOf(Customer::class, $user);
        $this->assertSame(123, $user->customerId());
        $this->assertSame('Alice Doe', $user->customerName());
        $this->assertSame('+201234567890', $user->customerPhone());
        $this->assertSame('alice@example.com', $user->customerEmail());
        $this->assertFalse($user->isGuest());
        $this->assertNull($user->deliveryAreaId());
    }

    public function test_user_customer_phone_returns_null_when_unset(): void
    {
        $user = new User([
            'name' => 'Bob Doe',
            'email' => 'bob@example.com',
        ]);
        $user->id = 456;

        $this->assertSame(456, $user->customerId());
        $this->assertSame('Bob Doe', $user->customerName());
        $this->assertNull($user->customerPhone());
        $this->assertSame('bob@example.com', $user->customerEmail());
        $this->assertFalse($user->isGuest());
        $this->assertNull($user->deliveryAreaId());
    }

    public function test_settings_repository_interface_definition(): void
    {
        $this->assertTrue(interface_exists(SettingsRepository::class));

        $reflection = new ReflectionClass(SettingsRepository::class);
        $this->assertTrue($reflection->hasMethod('get'));
        $this->assertTrue($reflection->hasMethod('set'));
        $this->assertTrue($reflection->hasMethod('has'));

        $getMethod = $reflection->getMethod('get');
        $setMethod = $reflection->getMethod('set');
        $hasMethod = $reflection->getMethod('has');

        $this->assertSame(2, $getMethod->getNumberOfParameters());
        $this->assertSame(2, $setMethod->getNumberOfParameters());
        $this->assertSame(1, $hasMethod->getNumberOfParameters());
    }
}
