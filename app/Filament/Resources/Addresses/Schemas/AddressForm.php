<?php

declare(strict_types=1);

namespace App\Filament\Resources\Addresses\Schemas;

use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;

final class AddressForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                self::getMainSection(),
                self::getAddressDetailsSection(),
            ]);
    }

    private static function getMainSection(): Component
    {
        return Section::make('المستخدم والمنطقة')
            ->schema([
                Grid::make(2)
                    ->schema([
                        Select::make('user_id')
                            ->label('المستخدم')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('area_id')
                            ->label('المنطقة')
                            ->relationship('area', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        TextInput::make('phone_number')
                            ->label('رقم الهاتف')
                            ->tel()
                            ->required()
                            ->maxLength(255),

                        Toggle::make('is_default')
                            ->label('عنوان افتراضي')
                            ->default(false),
                    ]),
            ]);
    }

    private static function getAddressDetailsSection(): Component
    {
        return Section::make('تفاصيل العنوان')
            ->schema([
                Grid::make(2)
                    ->schema([
                        TextInput::make('street')
                            ->label('الشارع')
                            ->maxLength(255),

                        TextInput::make('building')
                            ->label('المبنى')
                            ->maxLength(255),

                        TextInput::make('floor')
                            ->label('الدور')
                            ->maxLength(255),

                        TextInput::make('apartment')
                            ->label('الشقة')
                            ->maxLength(255),
                    ]),

                Textarea::make('full_address')
                    ->label('العنوان الكامل')
                    ->rows(3)
                    ->columnSpanFull(),

                Textarea::make('notes')
                    ->label('ملاحظات')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }
}
