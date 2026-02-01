<?php

declare(strict_types=1);

namespace App\Filament\Resources\BulkNotifications;

use App\Filament\Resources\BulkNotifications\Pages\CreateBulkNotification;
use App\Filament\Resources\BulkNotifications\Pages\ListBulkNotifications;
use App\Filament\Resources\BulkNotifications\Pages\ViewBulkNotification;
use App\Filament\Resources\BulkNotifications\Schemas\BulkNotificationForm;
use App\Filament\Resources\BulkNotifications\Schemas\BulkNotificationInfolist;
use App\Filament\Resources\BulkNotifications\Tables\BulkNotificationsTable;
use App\Models\BulkNotification;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

final class BulkNotificationResource extends Resource
{
    protected static ?string $model = BulkNotification::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBell;

    protected static ?string $navigationLabel = 'إشعارات جماعية';

    protected static ?string $modelLabel = 'إشعار جماعي';

    protected static ?string $pluralModelLabel = 'إشعارات جماعية';

    public static function form(Schema $schema): Schema
    {
        return BulkNotificationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return BulkNotificationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BulkNotificationsTable::configure($table);
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
            'index' => ListBulkNotifications::route('/'),
            'create' => CreateBulkNotification::route('/create'),
            'view' => ViewBulkNotification::route('/{record}'),
        ];
    }
}
