<?php

declare(strict_types=1);

namespace App\Plugins\ProductReviews\src\Filament\Resources;

use App\Plugins\ProductReviews\src\Filament\Resources\Pages\ListReviews;
use App\Plugins\ProductReviews\src\Models\Review;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

final class ReviewResource extends Resource
{
    use \App\Core\Plugins\Concerns\BelongsToPlugin;

    protected static ?string $model = Review::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static string|UnitEnum|null $navigationGroup = 'العملاء والتقييمات';

    protected static ?string $navigationLabel = 'التقييمات والآراء';

    protected static ?string $modelLabel = 'تقييم';

    protected static ?string $pluralModelLabel = 'التقييمات والآراء';

    protected static ?int $navigationSort = 3;

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
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('product.name')
                    ->label('المنتج')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('author_name')
                    ->label('صاحب التقييم')
                    ->searchable(['guest_name', 'user.name']),
                TextColumn::make('rating')
                    ->label('التقييم')
                    ->formatStateUsing(fn (int $state): string => str_repeat('⭐', $state)." ({$state}/5)")
                    ->sortable(),
                TextColumn::make('comment')
                    ->label('نص التعليق')
                    ->limit(60)
                    ->searchable(),
                IconColumn::make('is_approved')
                    ->label('معتمد')
                    ->boolean()
                    ->sortable(),
                ToggleColumn::make('is_published')
                    ->label('منشور'),
                TextColumn::make('created_at')
                    ->label('تاريخ الإرسال')
                    ->dateTime('d M Y - H:i')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_approved')
                    ->label('حالة الاعتماد')
                    ->trueLabel('معتمد')
                    ->falseLabel('معلق (بانتظار المراجعة)')
                    ->native(false),

                SelectFilter::make('rating')
                    ->label('عدد النجوم')
                    ->options([
                        5 => '⭐⭐⭐⭐⭐ (5 نجوم)',
                        4 => '⭐⭐⭐⭐ (4 نجوم)',
                        3 => '⭐⭐⭐ (3 نجوم)',
                        2 => '⭐⭐ (نجمتان)',
                        1 => '⭐ (نجمة واحدة)',
                    ]),

                SelectFilter::make('product_id')
                    ->label('المنتج')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->emptyStateHeading('لا توجد تقييمات')
            ->emptyStateDescription('لم يقم أي عميل بإرسال تقييم للمنتجات حتى الآن')
            ->recordActions([
                Action::make('approve')
                    ->label('اعتماد')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn ($record) => ! $record->is_approved)
                    ->action(fn ($record) => $record->update(['is_approved' => true])),

                Action::make('reject')
                    ->label('حجب')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn ($record) => (bool) $record->is_approved)
                    ->action(fn ($record) => $record->update(['is_approved' => false])),

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
