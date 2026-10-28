<?php

declare(strict_types=1);

namespace App\Plugins\CatalogProducts\src\Filament\Resources\Products;

use App\Plugins\CatalogProducts\src\Filament\Resources\Products\Pages\CreateProduct;
use App\Plugins\CatalogProducts\src\Filament\Resources\Products\Pages\EditProduct;
use App\Plugins\CatalogProducts\src\Filament\Resources\Products\Pages\ListProducts;
use App\Plugins\CatalogProducts\src\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Plugins\CatalogProducts\src\Filament\Resources\Products\Schemas\ProductForm;
use App\Plugins\CatalogProducts\src\Filament\Resources\Products\Tables\ProductsTable;
use App\Plugins\CatalogProducts\src\Models\Product;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

final class ProductResource extends Resource
{
    use \App\Core\Plugins\Concerns\BelongsToPlugin;

    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static string|UnitEnum|null $navigationGroup = 'الكتالوج والمنتجات';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'المنتجات';

    protected static ?string $modelLabel = 'منتج';

    protected static ?string $pluralModelLabel = 'المنتجات';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'name_ar'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Product $record */
        return array_filter([
            'الفئة' => $record->category?->name,
            'السعر' => $record->price !== null ? (string) $record->price : null,
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return ProductForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            VariantsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
