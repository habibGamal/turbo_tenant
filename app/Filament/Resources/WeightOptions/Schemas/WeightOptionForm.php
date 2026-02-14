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
                Section::make('معلومات أساسية')
                    ->schema([
                        TextInput::make('name')
                            ->label('الاسم')
                            ->required()
                            ->maxLength(255)
                            ->helperText('مثل: "الوزن القياسي"، "أحجام صغيرة"')
                            ->columnSpan(2),
                        Select::make('unit')
                            ->label('الوحدة')
                            ->options([
                                'kg' => 'كيلوجرام (kg)',
                                'g' => 'جرام (g)',
                                'lb' => 'رطل (lb)',
                            ])
                            ->default('kg')
                            ->required(),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),

                Section::make('قيم الوزن')
                    ->schema([
                        Repeater::make('values')
                            ->relationship()
                            ->schema([
                                TextInput::make('value')
                                    ->label('القيمة')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0.001)
                                    ->step(0.001)
                                    ->suffix(fn (callable $get) => $get('../../unit') ?? 'kg')
                                    ->helperText('قيمة الوزن (مثل: 0.25، 0.5، 1.0)')
                                    ->columnSpan(1),
                                TextInput::make('label')
                                    ->label('التسمية')
                                    ->maxLength(255)
                                    ->placeholder('تسمية اختيارية')
                                    ->helperText('مثل: "ربع كيلو"، "نصف كيلو"، "1 كيلو"')
                                    ->columnSpan(1),
                                TextInput::make('sort_order')
                                    ->numeric()
                                    ->default(0)
                                    ->required()
                                    ->label('ترتيب العرض')
                                    ->helperText('الأرقام الأقل تظهر أولاً')
                                    ->columnSpan(1),
                            ])
                            ->columns(3)
                            ->orderColumn('sort_order')
                            ->defaultItems(1)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['label'] ??
                                (isset($state['value']) ? "{$state['value']}" : null)
                            )
                            ->addActionLabel('إضافة قيمة وزن')
                            ->reorderable()
                            ->reorderableWithButtons()
                            ->deleteAction(
                                fn ($action) => $action->requiresConfirmation()
                            ),
                    ])
                    ->columnSpanFull()
                    ->description('حدد قيم وزن محددة يمكن للعملاء الاختيار منها. مثل: 0.25 كجم، 0.5 كجم، 1 كجم.'),
            ]);
    }
}
