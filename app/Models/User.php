<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Core\Contracts\Customer;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use NotificationChannels\Expo\ExpoPushToken;

final class User extends Authenticatable implements Customer, FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'avatar',
        'email_verified_at',
        'is_admin',
        'expo_token',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'app_authentication_secret',
        'app_authentication_recovery_codes',
    ];

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'admin') {
            return $this->is_admin;
        }

        return true;
    }

    public function getAppAuthenticationSecret(): ?string
    {
        return $this->app_authentication_secret;
    }

    public function saveAppAuthenticationSecret(?string $secret): void
    {
        $this->app_authentication_secret = $secret;
        $this->save();
    }

    public function getAppAuthenticationHolderName(): string
    {
        return $this->email;
    }

    /** @phpstan-ignore-next-line */
    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        /** @phpstan-ignore-next-line */
        return $this->app_authentication_recovery_codes;
    }

    public function saveAppAuthenticationRecoveryCodes(?array $codes): void
    {
        /** @phpstan-ignore-next-line  */
        $this->app_authentication_recovery_codes = $codes;
        $this->save();
    }

    public function routeNotificationForExpo(): ?ExpoPushToken
    {
        return $this->expo_token;
    }

    public function customerId(): int|string
    {
        return $this->getKey();
    }

    public function customerName(): ?string
    {
        $name = $this->getAttribute('name');

        return is_string($name) && $name !== '' ? $name : null;
    }

    public function customerPhone(): ?string
    {
        $phone = array_key_exists('phone', $this->attributes) ? $this->attributes['phone'] : null;

        return is_string($phone) && $phone !== '' ? $phone : null;
    }

    public function customerEmail(): ?string
    {
        $email = $this->getAttribute('email');

        return is_string($email) && $email !== '' ? $email : null;
    }

    public function isGuest(): bool
    {
        return false;
    }

    public function deliveryAreaId(): ?int
    {
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function customerAddress(): array
    {
        return [];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
            'is_admin' => 'boolean',
            'expo_token' => ExpoPushToken::class,
        ];
    }
}
