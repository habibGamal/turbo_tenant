<?php

declare(strict_types=1);

namespace App\Plugins\CatalogProducts\src\Filament\Resources\WeightOptions;

use App\Plugins\CatalogProducts\src\Filament\Resources\WeightOptions\Pages\CreateWeightOption;
use App\Plugins\CatalogProducts\src\Filament\Resources\WeightOptions\Pages\EditWeightOption;
use App\Plugins\CatalogProducts\src\Filament\Resources\WeightOptions\Pages\ListWeightOptions;
use App\Plugins\CatalogProducts\src\Filament\Resources\WeightOptions\Schemas\WeightOptionForm;
use App\Plugins\CatalogProducts\src\Filament\Resources\WeightOptions\Tables\WeightOptionsTable;
use App\Plugins\CatalogProducts\src\Models\WeightOption;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

final class WeightOptionResource extends Resource
{
    use \App\Core\Plugins\Concerns\BelongsToPlugin;

    protected static ?string $model = WeightOption::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = 'الكتالوج والمنتجات';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'خيارات الوزن';

    protected static ?string $modelLabel = 'خيار وزن';

    protected static ?string $pluralModelLabel = 'خيارات الوزن';

    public static function form(Schema $schema): Schema
    {
        return WeightOptionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WeightOptionsTable::configure($table);
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
            'index' => ListWeightOptions::route('/'),
            'create' => CreateWeightOption::route('/create'),
            'edit' => EditWeightOption::route('/{record}/edit'),
        ];
    }
}
