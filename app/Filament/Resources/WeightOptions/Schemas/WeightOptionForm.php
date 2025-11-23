<?php

declare(strict_types=1);

namespace App\Filament\Resources\WeightOptions\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class WeightOptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic Information')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->helperText('e.g., "Standard Weight", "Small Portions"')
                            ->columnSpan(2),
                        Select::make('unit')
                            ->options([
                                'kg' => 'Kilogram (kg)',
                                'g' => 'Gram (g)',
                                'lb' => 'Pound (lb)',
                            ])
                            ->default('kg')
                            ->required(),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),

                Section::make('Weight Values')
                    ->schema([
                        Repeater::make('values')
                            ->relationship()
                            ->schema([
                                TextInput::make('value')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0.001)
                                    ->step(0.001)
                                    ->suffix(fn (callable $get) => $get('../../unit') ?? 'kg')
                                    ->helperText('The weight value (e.g., 0.25, 0.5, 1.0)')
                                    ->columnSpan(1),
                                TextInput::make('label')
                                    ->maxLength(255)
                                    ->placeholder('Optional label')
                                    ->helperText('e.g., "Quarter kg", "Half kg", "1 kg"')
                                    ->columnSpan(1),
                                TextInput::make('sort_order')
                                    ->numeric()
                                    ->default(0)
                                    ->required()
                                    ->label('Sort Order')
                                    ->helperText('Lower numbers appear first')
                                    ->columnSpan(1),
                            ])
                            ->columns(3)
                            ->orderColumn('sort_order')
                            ->defaultItems(1)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string =>
                                $state['label'] ??
                                (isset($state['value']) ? "{$state['value']}" : null)
                            )
                            ->addActionLabel('Add Weight Value')
                            ->reorderable()
                            ->reorderableWithButtons()
                            ->deleteAction(
                                fn ($action) => $action->requiresConfirmation()
                            ),
                    ])
                    ->columnSpanFull()
                    ->description('Define specific weight values that customers can choose from. For example: 0.25 kg, 0.5 kg, 1 kg.'),
            ]);
    }
}
