<?php

declare(strict_types=1);

use App\Plugins\GuestCheckout\src\Models\GuestUser;

it('does not add a second country code to an international guest phone', function (): void {
    $guest = new GuestUser(['phone' => '+201000000000', 'phone_country_code' => '+20']);

    expect($guest->full_phone)->toBe('+201000000000')
        ->and($guest->customerAddress()['phone_number'])->toBe('+201000000000');
});

it('retains support for guest phones stored without an international prefix', function (): void {
    $guest = new GuestUser(['phone' => '1000000000', 'phone_country_code' => '+20']);

    expect($guest->full_phone)->toBe('+201000000000');
});
