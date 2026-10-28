<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Models\User;

final readonly class GoogleLoginResult
{
    private function __construct(
        private ?User $user,
        private ?GoogleLoginFailureReason $failureReason,
    ) {}

    public static function success(User $user): self
    {
        return new self($user, null);
    }

    public static function failure(GoogleLoginFailureReason $reason): self
    {
        return new self(null, $reason);
    }

    public function isSuccess(): bool
    {
        return $this->user !== null;
    }

    public function isFailure(): bool
    {
        return $this->failureReason !== null;
    }

    public function user(): ?User
    {
        return $this->user;
    }

    public function failureReason(): ?GoogleLoginFailureReason
    {
        return $this->failureReason;
    }

    public function errorMessage(): string
    {
        return $this->failureReason?->message() ?? '';
    }
}
