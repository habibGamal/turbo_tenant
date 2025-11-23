<?php

declare(strict_types=1);

namespace App\Filament\Resources\Branches\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class BranchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('link')
                            ->required()
                            ->rows(3)
                            ->maxLength(65535)
                            ->helperText('Enter the branch link or URL'),
                        Toggle::make('is_active')
                            ->default(true)
                            ->required(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
