<?php

declare(strict_types=1);

namespace App\Plugins\CmsPages\src\Filament\Resources\Pages;

use App\Plugins\CmsPages\src\Filament\Resources\Pages\Pages\CreatePage;
use App\Plugins\CmsPages\src\Filament\Resources\Pages\Pages\EditPage;
use App\Plugins\CmsPages\src\Filament\Resources\Pages\Pages\ListPages;
use App\Plugins\CmsPages\src\Filament\Resources\Pages\Pages\ViewPage;
use App\Plugins\CmsPages\src\Filament\Resources\Pages\Schemas\PageForm;
use App\Plugins\CmsPages\src\Filament\Resources\Pages\Schemas\PageInfolist;
use App\Plugins\CmsPages\src\Filament\Resources\Pages\Tables\PagesTable;
use App\Plugins\CmsPages\src\Models\Page;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'الصفحات';

    protected static ?string $modelLabel = 'صفحة';

    protected static ?string $pluralModelLabel = 'الصفحات';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return PageForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PageInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PagesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPages::route('/'),
            'create' => CreatePage::route('/create'),
            'view' => ViewPage::route('/{record}'),
            'edit' => EditPage::route('/{record}/edit'),
        ];
    }
}
