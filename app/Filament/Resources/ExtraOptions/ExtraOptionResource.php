<?php

declare(strict_types=1);

namespace App\Filament\Resources\ExtraOptions;

use App\Filament\Resources\ExtraOptions\Pages\CreateExtraOption;
use App\Filament\Resources\ExtraOptions\Pages\EditExtraOption;
use App\Filament\Resources\ExtraOptions\Pages\ListExtraOptions;
use App\Filament\Resources\ExtraOptions\Schemas\ExtraOptionForm;
use App\Filament\Resources\ExtraOptions\Tables\ExtraOptionsTable;
use App\Models\ExtraOption;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

final class ExtraOptionResource extends Resource
{
    protected static ?string $model = ExtraOption::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'الخيارات الإضافية';

    protected static ?string $modelLabel = 'خيار إضافي';

    protected static ?string $pluralModelLabel = 'الخيارات الإضافية';

    public static function form(Schema $schema): Schema
    {
        return ExtraOptionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExtraOptionsTable::configure($table);
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
            'index' => ListExtraOptions::route('/'),
            'create' => CreateExtraOption::route('/create'),
            'edit' => EditExtraOption::route('/{record}/edit'),
        ];
    }
}
