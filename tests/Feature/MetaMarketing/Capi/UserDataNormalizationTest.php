<?php

declare(strict_types=1);

use App\Models\User;
use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\MetaMarketing\src\Capi\UserDataBuilder;
use App\Plugins\MetaMarketing\src\Models\MetaOrderTracking;
use App\Plugins\OrderManagement\src\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('phone normalization handles local Egyptian numbers, international prefixes, spaces and dashes', function () {
    $builder = new UserDataBuilder();

    // 010... local -> 2010...
    expect($builder->normalizePhone('01012345678'))->toBe('201012345678')
        ->and($builder->normalizePhone('01112345678'))->toBe('201112345678')
        ->and($builder->normalizePhone('01212345678'))->toBe('201212345678')
        ->and($builder->normalizePhone('01512345678'))->toBe('201512345678');

    // +2010... -> 2010...
    expect($builder->normalizePhone('+201012345678'))->toBe('201012345678')
        ->and($builder->normalizePhone('+201112345678'))->toBe('201112345678');

    // 2010... stays 2010...
    expect($builder->normalizePhone('201012345678'))->toBe('201012345678');

    // Spaces and dashes
    expect($builder->normalizePhone('010-1234-5678'))->toBe('201012345678')
        ->and($builder->normalizePhone('+20 101 234 5678'))->toBe('201012345678')
        ->and($builder->normalizePhone('010 1234 5678'))->toBe('201012345678')
        ->and($builder->normalizePhone('+20-10-1234-5678'))->toBe('201012345678');

    // 0020 international prefix
    expect($builder->normalizePhone('00201012345678'))->toBe('201012345678');

    // Empty / null / letters
    expect($builder->normalizePhone(null))->toBeNull()
        ->and($builder->normalizePhone(''))->toBeNull()
        ->and($builder->normalizePhone('not-a-number'))->toBeNull();
});

test('email normalization trims and lowercases before hashing', function () {
    $builder = new UserDataBuilder();

    expect($builder->normalizeEmail('  TEST@Example.COM  '))->toBe('test@example.com')
        ->and($builder->normalizeEmail(null))->toBeNull()
        ->and($builder->normalizeEmail('   '))->toBeNull();
});

test('split name separates first and last name on first space', function () {
    $builder = new UserDataBuilder();

    expect($builder->splitName('Ahmed Mohamed Ali'))->toBe(['ahmed', 'mohamed ali'])
        ->and($builder->splitName('Ahmed'))->toBe(['ahmed', null])
        ->and($builder->splitName('  Salma   Mahmoud '))->toBe(['salma', 'mahmoud'])
        ->and($builder->splitName(null))->toBe([null, null])
        ->and($builder->splitName('   '))->toBe([null, null]);
});

test('city normalization lowercases and removes spaces', function () {
    $builder = new UserDataBuilder();

    expect($builder->normalizeCity('Nasr City'))->toBe('nasrcity')
        ->and($builder->normalizeCity('  New Cairo  '))->toBe('newcairo')
        ->and($builder->normalizeCity(null))->toBeNull();
});

test('user data for order hashes PII and keeps fbp fbc raw', function () {
    $builder = new UserDataBuilder();

    $branch = Branch::factory()->create();
    $user = User::factory()->create([
        'name' => 'Kareem Tarek',
        'email' => 'kareem@example.com',
    ]);

    $order = Order::factory()->create([
        'branch_id' => $branch->id,
        'user_id' => $user->id,
        'shift_id' => null,
        'address_id' => null,
        'coupon_id' => null,
        'customer_name' => 'Kareem Tarek',
        'customer_email' => 'kareem@example.com',
        'customer_phone' => '01012345678',
        'delivery_address' => [
            'city' => 'Cairo',
            'street' => 'Tahrir',
        ],
    ]);

    $tracking = new MetaOrderTracking([
        'order_id' => $order->id,
        'client_ip' => '192.168.1.10',
        'user_agent' => 'Mozilla/5.0 Test',
        'fbp' => 'fb.1.12345.67890',
        'fbc' => 'fb.1.12345.abcdef',
    ]);

    $userData = $builder->forOrder($order, $tracking);

    expect($userData)->toHaveKeys([
        'em',
        'ph',
        'fn',
        'ln',
        'ct',
        'country',
        'external_id',
        'client_ip_address',
        'client_user_agent',
        'fbp',
        'fbc',
    ]);

    // Hashed arrays
    expect($userData['em'])->toBe([hash('sha256', 'kareem@example.com')])
        ->and($userData['ph'])->toBe([hash('sha256', '201012345678')])
        ->and($userData['fn'])->toBe([hash('sha256', 'kareem')])
        ->and($userData['ln'])->toBe([hash('sha256', 'tarek')])
        ->and($userData['ct'])->toBe([hash('sha256', 'cairo')])
        ->and($userData['country'])->toBe([hash('sha256', 'eg')])
        ->and($userData['external_id'])->toBe([hash('sha256', 'u:'.$user->id)]);

    // Raw unhashed values
    expect($userData['client_ip_address'])->toBe('192.168.1.10')
        ->and($userData['client_user_agent'])->toBe('Mozilla/5.0 Test')
        ->and($userData['fbp'])->toBe('fb.1.12345.67890')
        ->and($userData['fbc'])->toBe('fb.1.12345.abcdef');
});

test('user data for guest order uses guest user id prefix', function () {
    $builder = new UserDataBuilder();
    $branch = Branch::factory()->create();

    $guestId = DB::table('guest_users')->insertGetId([
        'name' => 'Guest Person',
        'email' => 'guest@example.com',
        'phone' => '1012345678',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $order = Order::factory()->create([
        'branch_id' => $branch->id,
        'user_id' => null,
        'guest_user_id' => $guestId,
        'shift_id' => null,
        'address_id' => null,
        'coupon_id' => null,
        'customer_name' => 'Guest Person',
        'customer_email' => 'guest@example.com',
        'customer_phone' => '+201012345678',
    ]);

    $userData = $builder->forOrder($order);

    expect($userData['external_id'])->toBe([hash('sha256', 'g:'.$guestId)]);
});
