<?php

declare(strict_types=1);

namespace App\Plugins\HeroBanners\src\Filament\Resources;

use App\Plugins\HeroBanners\src\Models\HeroSlider;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

final class HeroSliderResource extends Resource
{
    use \App\Core\Plugins\Concerns\BelongsToPlugin;

    protected static ?string $model = HeroSlider::class;

    protected static string|UnitEnum|null $navigationGroup = 'المحتوى والتسويق';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $navigationLabel = 'سلايدر';

    protected static ?string $modelLabel = 'سلايدر';

    protected static ?string $pluralModelLabel = 'سلايدر';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('محتوى السلايدر')
                    ->tabs([
                        Tab::make('العربية')
                            ->schema([
                                TextInput::make('title_ar')
                                    ->label('العنوان الرئيسي (عربي)')
                                    ->maxLength(255),
                                TextInput::make('subtitle_ar')
                                    ->label('العنوان الفرعي (عربي)')
                                    ->maxLength(255),
                                TextInput::make('badge_ar')
                                    ->label('الشارة الترويجية (عربي)')
                                    ->maxLength(255),
                                TextInput::make('cta_text_ar')
                                    ->label('نص زر الإجراء (CTA عربي)')
                                    ->maxLength(255),
                                TextInput::make('secondary_cta_text_ar')
                                    ->label('نص الزر الثانوي (عربي)')
                                    ->maxLength(255),
                            ]),
                        Tab::make('English')
                            ->schema([
                                TextInput::make('title')
                                    ->label('العنوان الرئيسي (English)')
                                    ->maxLength(255),
                                TextInput::make('subtitle')
                                    ->label('العنوان الفرعي (English)')
                                    ->maxLength(255),
                                TextInput::make('badge')
                                    ->label('الشارة الترويجية (English)')
                                    ->maxLength(255),
                                TextInput::make('cta_text')
                                    ->label('نص زر الإجراء (CTA English)')
                                    ->maxLength(255),
                                TextInput::make('secondary_cta_text')
                                    ->label('نص الزر الثانوي (English)')
                                    ->maxLength(255),
                            ]),
                    ])->columnSpanFull(),

                Section::make('الروابط والوسائط')
                    ->schema([
                        TextInput::make('cta_link')
                            ->label('رابط زر الإجراء (CTA)')
                            ->maxLength(255),
                        TextInput::make('secondary_cta_link')
                            ->label('رابط الزر الثانوي')
                            ->maxLength(255),
                        FileUpload::make('image')
                            ->label('صورة السلايدر')
                            ->image()
                            ->optimizeForWeb('hero-sliders')
                            ->required(),
                        Select::make('gradient')
                            ->label('التدرج اللوني للخلفية')
                            ->options([
                                'from-orange-500/20 via-red-500/10 to-pink-500/20' => 'برتقالي وأحمر دافئ (Sunset)',
                                'from-emerald-500/20 via-teal-500/10 to-cyan-500/20' => 'أخضر وزمردي منعش (Fresh)',
                                'from-blue-500/20 via-indigo-500/10 to-purple-500/20' => 'أزرق وبنفسجي ملكي (Royal)',
                                'from-amber-500/20 via-yellow-500/10 to-orange-500/20' => 'ذهبي ومميز (Gold)',
                                'from-zinc-500/20 via-neutral-500/10 to-stone-500/20' => 'رمادي كلاسيكي (Neutral)',
                            ])
                            ->default('from-orange-500/20 via-red-500/10 to-pink-500/20'),
                        Select::make('icon')
                            ->label('الأيقونة')
                            ->options([
                                'sparkles' => 'بريق (Sparkles)',
                                'timer' => 'مؤقت / وقت محدد (Timer)',
                                'trending' => 'رائج / مميز (Trending)',
                                'fire' => 'لهب (Fire)',
                                'gift' => 'هدية (Gift)',
                            ])
                            ->default('sparkles'),
                    ])->columns(2),

                Section::make('إعدادات العرض والحالة')
                    ->schema([
                        TextInput::make('sort_order')
                            ->label('ترتيب العرض')
                            ->numeric()
                            ->default(0),
                        Toggle::make('is_active')
                            ->label('نشط')
                            ->default(true)
                            ->required(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order', 'asc')
            ->columns([
                ImageColumn::make('image')
                    ->label('الصورة'),
                TextColumn::make('title_ar')
                    ->label('العنوان (عربي)')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title')
                    ->label('العنوان (إنجليزي)')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('subtitle_ar')
                    ->label('العنوان الفرعي (عربي)')
                    ->limit(40)
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('cta_link')
                    ->label('رابط التوجيه')
                    ->badge()
                    ->limit(25)
                    ->toggleable(),
                ToggleColumn::make('is_active')
                    ->label('نشط')
                    ->sortable(),
                TextColumn::make('sort_order')
                    ->label('ترتيب العرض')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('تاريخ التحديث')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('الحالة')
                    ->boolean()
                    ->trueLabel('نشط فقط')
                    ->falseLabel('غير نشط فقط')
                    ->native(false),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('لا توجد عناصر سلايدر')
            ->emptyStateDescription('أضف بنرات وسلايدر ترويجي للترويج لأحدث العروض والمنتجات في متجرك.')
            ->emptyStateIcon('heroicon-o-photo')
            ->emptyStateActions([
                Action::make('create')
                    ->label('إضافة سلايدر جديد')
                    ->url(fn () => self::getUrl('create'))
                    ->icon('heroicon-m-plus')
                    ->button(),
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
