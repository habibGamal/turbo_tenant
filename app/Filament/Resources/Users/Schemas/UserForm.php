<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Schemas;

use App\Filament\Resources\Users\Pages\CreateUser;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

final class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('بيانات العميل الحسابية')
                    ->schema([
                        TextInput::make('name')
                            ->label('الاسم الكامل')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('البريد الإلكتروني')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        TextInput::make('password')
                            ->label('كلمة المرور')
                            ->password()
                            ->required(fn ($livewire): bool => $livewire instanceof CreateUser)
                            ->revealable(filament()->arePasswordsRevealable())
                            ->rule(Password::default())
                            ->autocomplete('new-password')
                            ->dehydrated(fn ($state): bool => filled($state))
                            ->dehydrateStateUsing(fn ($state): string => Hash::make($state)),
                        Toggle::make('is_admin')
                            ->label('صلاحية مدير نظام')
                            ->disabled(fn ($record): bool => $record !== null && auth()->check() && auth()->id() === $record->id)
                            ->helperText(fn ($record): string => $record !== null && auth()->check() && auth()->id() === $record->id
                                ? 'لا يمكنك إلغاء صلاحية المدير لنفسك'
                                : 'تحديد ما إذا كان هذا الحساب يملك صلاحيات لوحة التحكم')
                            ->default(false),
                    ])
                    ->columns(2),
            ]);
    }
}
