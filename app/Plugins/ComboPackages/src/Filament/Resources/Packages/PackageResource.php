<?php

declare(strict_types=1);

namespace App\Plugins\ComboPackages\src\Filament\Resources\Packages;

use App\Plugins\ComboPackages\src\Filament\Resources\Packages\Pages\CreatePackage;
use App\Plugins\ComboPackages\src\Filament\Resources\Packages\Pages\EditPackage;
use App\Plugins\ComboPackages\src\Filament\Resources\Packages\Pages\ListPackages;
use App\Plugins\ComboPackages\src\Filament\Resources\Packages\Schemas\PackageForm;
use App\Plugins\ComboPackages\src\Filament\Resources\Packages\Tables\PackagesTable;
use App\Plugins\ComboPackages\src\Models\Package;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

final class PackageResource extends Resource
{
    use \App\Core\Plugins\Concerns\BelongsToPlugin;

    protected static ?string $model = Package::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|UnitEnum|null $navigationGroup = 'العروض والخصومات';

    protected static ?string $navigationLabel = 'باقات التوفير';

    protected static ?string $modelLabel = 'باقة';

    protected static ?string $pluralModelLabel = 'باقات التوفير';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return PackageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PackagesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPackages::route('/'),
            'create' => CreatePackage::route('/create'),
            'edit' => EditPackage::route('/{record}/edit'),
        ];
    }
}
