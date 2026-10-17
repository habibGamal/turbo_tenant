<?php

declare(strict_types=1);

namespace App\Plugins\ProductReviews\src\Filament\Resources;

use App\Plugins\ProductReviews\src\Filament\Resources\Pages\ListReviews;
use App\Plugins\ProductReviews\src\Models\Review;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static ?string $navigationLabel = 'التقييمات والآراء';

    protected static ?string $modelLabel = 'تقييم';

    protected static ?string $pluralModelLabel = 'التقييمات';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('rating')
                    ->label('التقييم')
                    ->numeric()
                    ->required(),
                Textarea::make('comment')
                    ->label('التعليق')
                    ->required()
                    ->columnSpanFull(),
                Toggle::make('is_published')
                    ->label('منشور')
                    ->default(true),
                Toggle::make('is_approved')
                    ->label('معتمد')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')
                    ->label('الوجبة / المنتج')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('author_name')
                    ->label('الكاتب')
                    ->searchable(['guest_name', 'user.name']),
                TextColumn::make('rating')
                    ->label('التقييم')
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state >= 4 => 'success',
                        $state === 3 => 'warning',
                        default => 'danger',
                    })
                    ->sortable(),
                TextColumn::make('comment')
                    ->label('التعليق')
                    ->limit(50)
                    ->searchable(),
                IconColumn::make('is_published')
                    ->label('منشور')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReviews::route('/'),
        ];
    }
}
