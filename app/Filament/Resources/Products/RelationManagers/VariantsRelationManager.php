<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('price')
                    ->numeric()
                    ->prefix('$')
                    ->maxValue(99999.99),
                Toggle::make('is_available')
                    ->default(true),
                TextInput::make('sort_order')
                    ->numeric()
                    ->default(0)
                    ->label('Sort Order'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('price')
                    ->money('EGP')
                    ->sortable(),
                IconColumn::make('is_available')
                    ->boolean()
                    ->sortable()
                    ->label('Available'),
                TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable()
                    ->label('Sort Order'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Action::make('quickAdd')
                    ->label('Quick Add Variants')
                    ->icon('heroicon-o-bolt')
                    ->color('info')
                    ->form([
                        TagsInput::make('variant_names')
                            ->label('Variant Names')
                            ->placeholder('Type variant name and press Enter')
                            ->helperText('Add variant names quickly. Press Enter after each name. Variants will be created with default settings.')
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        $product = $this->getOwnerRecord();
                        $variantNames = $data['variant_names'];

                        $maxSortOrder = $product->variants()->max('sort_order') ?? 0;

                        foreach ($variantNames as $index => $name) {
                            $product->variants()->create([
                                'name' => $name,
                                'is_available' => true,
                                'sort_order' => $maxSortOrder + $index + 1,
                            ]);
                        }

                        Notification::make()
                            ->title('Variants created successfully')
                            ->body(count($variantNames) . ' variant(s) added.')
                            ->success()
                            ->send();
                    })
                    ->successNotificationTitle('Variants created')
                    ->modalWidth('md'),
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order', 'asc');
    }
}
