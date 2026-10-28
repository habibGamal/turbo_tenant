<?php

declare(strict_types=1);

namespace App\Support\Auth;

enum GoogleLoginFailureReason: string
{
    case LinkRequired = 'link_required';
    case AccountMismatch = 'account_mismatch';
    case EmailNotVerified = 'email_not_verified';

    public function message(): string
    {
        return match ($this) {
            self::LinkRequired => 'An account with this email already exists. Please log in with your password.',
            self::AccountMismatch => 'Google account does not match this user.',
            self::EmailNotVerified => 'Your Google email is not verified.',
        };
    }
}
