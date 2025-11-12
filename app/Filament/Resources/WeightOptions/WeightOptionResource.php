<?php

declare(strict_types=1);

namespace App\Filament\Resources\WeightOptions;

use App\Filament\Resources\WeightOptions\Pages\CreateWeightOption;
use App\Filament\Resources\WeightOptions\Pages\EditWeightOption;
use App\Filament\Resources\WeightOptions\Pages\ListWeightOptions;
use App\Filament\Resources\WeightOptions\Schemas\WeightOptionForm;
use App\Filament\Resources\WeightOptions\Tables\WeightOptionsTable;
use App\Models\WeightOption;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

final class WeightOptionResource extends Resource
{
    protected static ?string $model = WeightOption::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

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
