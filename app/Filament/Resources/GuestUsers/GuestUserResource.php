<?php

declare(strict_types=1);

namespace App\Filament\Resources\GuestUsers;

use App\Filament\Resources\GuestUsers\Pages\ListGuestUsers;
use App\Filament\Resources\GuestUsers\Pages\ViewGuestUser;
use App\Filament\Resources\GuestUsers\Schemas\GuestUserInfolist;
use App\Filament\Resources\GuestUsers\Tables\GuestUsersTable;
use App\Models\GuestUser;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

final class GuestUserResource extends Resource
{
    protected static ?string $model = GuestUser::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'الزوار';

    protected static ?string $modelLabel = 'زائر';

    protected static ?string $pluralModelLabel = 'الزوار';

    protected static ?int $navigationSort = 10;

    public static function infolist(Schema $schema): Schema
    {
        return GuestUserInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GuestUsersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGuestUsers::route('/'),
            'view' => ViewGuestUser::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }
}
