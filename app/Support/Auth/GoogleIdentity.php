<?php

declare(strict_types=1);

namespace App\Support\Auth;

final readonly class GoogleIdentity
{
    public function __construct(
        public string $googleId,
        public string $email,
        public ?string $name = null,
        public ?string $avatar = null,
        public bool $emailVerified = false,
    ) {}
}
