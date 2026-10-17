<?php

declare(strict_types=1);

namespace App\Plugins\LocationsDelivery\src\Filament\Resources\Branches;

use App\Plugins\LocationsDelivery\src\Filament\Resources\Branches\Pages\CreateBranch;
use App\Plugins\LocationsDelivery\src\Filament\Resources\Branches\Pages\EditBranch;
use App\Plugins\LocationsDelivery\src\Filament\Resources\Branches\Pages\ListBranches;
use App\Plugins\LocationsDelivery\src\Filament\Resources\Branches\Schemas\BranchForm;
use App\Plugins\LocationsDelivery\src\Filament\Resources\Branches\Tables\BranchesTable;
use App\Plugins\LocationsDelivery\src\Models\Branch;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

final class BranchResource extends Resource
{
    protected static ?string $model = Branch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $navigationLabel = 'الفروع';

    protected static ?string $modelLabel = 'فرع';

    protected static ?string $pluralModelLabel = 'الفروع';

    public static function form(Schema $schema): Schema
    {
        return BranchForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BranchesTable::configure($table);
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
            'index' => ListBranches::route('/'),
            'create' => CreateBranch::route('/create'),
            'edit' => EditBranch::route('/{record}/edit'),
        ];
    }
}
