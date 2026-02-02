<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HeroSliderResource\Pages;
use App\Models\HeroSlider;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Support\Icons\Heroicon;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class HeroSliderResource extends Resource
{
    protected static ?string $model = HeroSlider::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $navigationLabel = 'سلايدر الترويسة';

    protected static ?string $modelLabel = 'سلايدر';

    protected static ?string $pluralModelLabel = 'سلايدر الترويسة';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Content')
                    ->tabs([
                        Tab::make('English')
                            ->schema([
                                TextInput::make('title')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('subtitle')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('badge')
                                    ->maxLength(255),
                                TextInput::make('cta_text')
                                    ->label('CTA Text')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('secondary_cta_text')
                                    ->label('Secondary CTA Text')
                                    ->maxLength(255),
                            ]),
                        Tab::make('Arabic')
                            ->schema([
                                TextInput::make('title_ar')
                                    ->label('Title (Arabic)')
                                    ->maxLength(255),
                                TextInput::make('subtitle_ar')
                                    ->label('Subtitle (Arabic)')
                                    ->maxLength(255),
                                TextInput::make('badge_ar')
                                    ->label('Badge (Arabic)')
                                    ->maxLength(255),
                                TextInput::make('cta_text_ar')
                                    ->label('CTA Text (Arabic)')
                                    ->maxLength(255),
                                TextInput::make('secondary_cta_text_ar')
                                    ->label('Secondary CTA Text (Arabic)')
                                    ->maxLength(255),
                            ]),
                    ])->columnSpanFull(),

                Section::make('Links & Media')
                    ->schema([
                        TextInput::make('cta_link')
                            ->label('CTA Link')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('secondary_cta_link')
                            ->label('Secondary CTA Link')
                            ->maxLength(255),
                        FileUpload::make('image')
                            ->image()
                            ->directory('hero-sliders'),
                        TextInput::make('gradient')
                            ->default('from-orange-500/20 via-red-500/10 to-pink-500/20')
                            ->required()
                            ->maxLength(255),
                        Select::make('icon')
                            ->options([
                                'sparkles' => 'Sparkles',
                                'timer' => 'Timer',
                                'trending' => 'Trending',
                            ])
                            ->default('sparkles')
                            ->required(),
                    ])->columns(2),

                Section::make('Settings')
                    ->schema([
                        TextInput::make('sort_order')
                            ->numeric()
                            ->default(0),
                        Toggle::make('is_active')
                            ->default(true)
                            ->required(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image'),
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('subtitle')
                    ->limit(50)
                    ->searchable(),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable(),
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
            'index' => Pages\ListHeroSliders::route('/'),
            'create' => Pages\CreateHeroSlider::route('/create'),
            'edit' => Pages\EditHeroSlider::route('/{record}/edit'),
        ];
    }
}
