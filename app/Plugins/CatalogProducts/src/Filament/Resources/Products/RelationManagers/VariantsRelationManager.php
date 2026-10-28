<?php

declare(strict_types=1);

namespace App\Plugins\CatalogProducts\src\Filament\Resources\Products\RelationManagers;

use App\Plugins\StoreSettings\src\Services\SettingService;
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
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

final class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    protected static ?string $title = 'التنويعات';

    protected static ?string $modelLabel = 'تنويع';

    protected static ?string $pluralModelLabel = 'التنويعات';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('الاسم')
                    ->required()
                    ->maxLength(255),
                TextInput::make('name_ar')
                    ->label('الاسم بالعربية')
                    ->maxLength(255),
                TextInput::make('price')
                    ->label('السعر')
                    ->numeric()
                    ->prefix(fn () => class_exists(SettingService::class) ? app(SettingService::class)->get('currency', 'EGP') : 'EGP')
                    ->maxValue(99999.99)
                    ->helperText('اتركه فارغاً لاستخدام السعر الأساسي للمنتج'),
                Toggle::make('is_available')
                    ->label('متاح للطلب')
                    ->default(true),
                TextInput::make('sort_order')
                    ->numeric()
                    ->default(0)
                    ->label('ترتيب العرض'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('sort_order', 'asc')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name_ar')
                    ->label('الاسم بالعربية')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('price')
                    ->label('السعر')
                    ->money(fn () => class_exists(SettingService::class) ? app(SettingService::class)->get('currency', 'EGP') : 'EGP')
                    ->sortable()
                    ->description(fn ($record) => $record->price !== null && $record->product?->base_price
                        ? (($diff = (float) $record->price - (float) $record->product->base_price) === 0.0
                            ? 'نفس السعر الأساسي'
                            : ($diff > 0 ? '+'.number_format($diff, 2) : number_format($diff, 2)))
                        : null
                    ),
                ToggleColumn::make('is_available')
                    ->label('متاح')
                    ->sortable(),
                TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable()
                    ->label('ترتيب العرض'),
            ])
            ->filters([
                TernaryFilter::make('is_available')
                    ->label('حالة التوفر')
                    ->trueLabel('متاح فقط')
                    ->falseLabel('غير متاح فقط'),
            ])
            ->headerActions([
                Action::make('quickAdd')
                    ->label('إضافة سريعة للتنويعات')
                    ->icon('heroicon-o-bolt')
                    ->color('info')
                    ->form([
                        TagsInput::make('variant_names')
                            ->label('أسماء التنويعات')
                            ->placeholder('أدخل اسم التنويع واضغط Enter')
                            ->helperText('أضف أسماء التنويعات بسرعة. اضغط Enter بعد كل اسم. سيتم إنشاء التنويعات بالإعدادات الافتراضية.')
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
                            ->title('تم إنشاء التنويعات بنجاح')
                            ->body(count($variantNames).' تنويع(ات) تمت إضافتها.')
                            ->success()
                            ->send();
                    })
                    ->successNotificationTitle('تم إنشاء التنويعات')
                    ->modalWidth('md'),
                CreateAction::make()
                    ->label('إضافة تنويع'),
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
            ->emptyStateHeading('لا توجد تنويعات')
            ->emptyStateDescription('أضف تنويعات لهذا المنتج مثل الأحجام أو النكهات.')
            ->emptyStateIcon('heroicon-o-adjustments-horizontal')
            ->emptyStateActions([
                CreateAction::make()
                    ->label('إضافة أول تنويع'),
            ]);
    }
}
