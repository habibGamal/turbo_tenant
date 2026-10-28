<?php

declare(strict_types=1);

namespace App\Plugins\CatalogProducts\src\Filament\Resources\Sections;

use App\Plugins\CatalogProducts\src\Filament\Resources\Sections\Pages\CreateSection;
use App\Plugins\CatalogProducts\src\Filament\Resources\Sections\Pages\EditSection;
use App\Plugins\CatalogProducts\src\Filament\Resources\Sections\Pages\ListSections;
use App\Plugins\CatalogProducts\src\Filament\Resources\Sections\Schemas\SectionForm;
use App\Plugins\CatalogProducts\src\Filament\Resources\Sections\Tables\SectionsTable;
use App\Plugins\CatalogProducts\src\Models\Section;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

final class SectionResource extends Resource
{
    use \App\Core\Plugins\Concerns\BelongsToPlugin;

    protected static ?string $model = Section::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'الكتالوج والمنتجات';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'أقسام الواجهة';

    protected static ?string $modelLabel = 'قسم';

    protected static ?string $pluralModelLabel = 'الأقسام';

    public static function form(Schema $schema): Schema
    {
        return SectionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SectionsTable::configure($table);
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
            'index' => ListSections::route('/'),
            'create' => CreateSection::route('/create'),
            'edit' => EditSection::route('/{record}/edit'),
        ];
    }
}
