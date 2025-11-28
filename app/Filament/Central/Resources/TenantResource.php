<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources;

use App\Filament\Central\Resources\TenantResource\Pages;
use App\Models\Tenant;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $navigationLabel = 'Tenants';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Tenant Information')
                    ->schema([
                        TextInput::make('id')
                            ->label('Tenant ID')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->alphaDash()
                            ->helperText('A unique identifier for the tenant (e.g., restaurant-name). Only letters, numbers, dashes, and underscores.')
                            ->disabled(fn (string $operation): bool => $operation === 'edit'),
                    ]),

                Section::make('Domains')
                    ->schema([
                        Repeater::make('domains')
                            ->relationship()
                            ->schema([
                                TextInput::make('domain')
                                    ->label('Domain')
                                    ->required()
                                    ->maxLength(255)
                                    ->helperText('e.g., restaurant.localhost or restaurant.example.com'),
                            ])
                            ->defaultItems(1)
                            ->minItems(1)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->headerActions([
               CreateAction::make(),
            ])
            ->columns([
                TextColumn::make('id')
                    ->label('Tenant ID')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('domains.domain')
                    ->label('Domains')
                    ->badge()
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }
}
