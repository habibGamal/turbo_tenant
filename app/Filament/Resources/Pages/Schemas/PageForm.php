<?php

namespace App\Filament\Resources\Pages\Schemas;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('المحتوى')
                    ->tabs([
                        Tab::make('إنجليزي')
                            ->schema([
                                TextInput::make('title')
                                    ->label('العنوان')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn(string $operation, $state, $set) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),
                                RichEditor::make('content')
                                    ->label('المحتوى')
                                    ->required(),
                            ]),
                        Tab::make('عربي')
                            ->schema([
                                TextInput::make('title_ar')
                                    ->label('العنوان (عربي)'),
                                RichEditor::make('content_ar')
                                    ->label('المحتوى (عربي)'),
                            ]),
                    ])->columnSpanFull(),
                TextInput::make('slug')
                    ->label('رابط مختصر')
                    ->required()
                    ->unique(ignoreRecord: true),
                Toggle::make('is_active')
                    ->label('نشط')
                    ->required()
                    ->default(true),
            ]);
    }
}
