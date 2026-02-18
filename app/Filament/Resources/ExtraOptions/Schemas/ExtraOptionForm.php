<?php

declare(strict_types=1);

namespace App\Filament\Resources\ExtraOptions\Schemas;

use App\Filament\Forms\Components\PosItemSelect;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class ExtraOptionForm
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
                            ->maxLength(255),
                        TextInput::make('name_ar')
                            ->label('الاسم بالعربية')
                            ->maxLength(255),
                        Textarea::make('description')
                            ->label('الوصف')
                            ->rows(3)
                            ->maxLength(65535),
                        Toggle::make('is_active')
                            ->label('نشط')
                            ->default(true)
                            ->required(),
                    ])
                    ->columnSpanFull(),

                Section::make('عناصر الخيار')
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->schema([
                                TextInput::make('name')
                                    ->label('الاسم')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(2),
                                TextInput::make('name_ar')
                                    ->label('الاسم بالعربية')
                                    ->maxLength(255)
                                    ->columnSpan(2),
                                TextInput::make('price')
                                    ->label('السعر')
                                    ->numeric()
                                    ->default(0)
                                    ->prefix('$')
                                    ->required(),
                                Toggle::make('is_default')
                                    ->default(false)
                                    ->label('الاختيار الافتراضي'),
                                TextInput::make('sort_order')
                                    ->numeric()
                                    ->default(0)
                                    ->label('ترتيب العرض'),
                                Radio::make('pos_mapping_type')
                                    ->options([
                                        'pos_item' => 'عنصر POS',
                                        'notes' => 'ملاحظات',
                                    ])
                                    ->default('pos_item')
                                    ->inline()
                                    ->required()
                                    ->label('نوع ربط POS')
                                    ->live(),
                                Toggle::make('allow_quantity')
                                    ->default(false)
                                    ->label('السماح باختيار الكمية')
                                    ->helperText('السماح للعملاء باختيار كمية لهذا العنصر'),
                                Repeater::make('posMappings')
                                    ->relationship()
                                    ->schema([
                                        PosItemSelect::make('pos_item_id')
                                            ->columnSpan(2),
                                        Select::make('branch_id')
                                            ->relationship('branch', 'name')
                                            ->label('الفرع (اختياري)')
                                            ->helperText('اتركه فارغاً لجميع الفروع'),
                                    ])
                                    ->columns(3)
                                    ->defaultItems(0)
                                    ->addActionLabel('إضافة ربط POS')
                                    ->collapsible()
                                    ->visible(fn (callable $get) => $get('pos_mapping_type') === 'pos_item')
                                    ->columnSpanFull(),
                            ])
                            ->columns(3)
                            ->orderColumn('sort_order')
                            ->defaultItems(1)
                            ->collapsible(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
