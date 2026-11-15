<?php

declare(strict_types=1);

namespace App\Plugins\MetaMarketing\src\Settings;

use App\Core\Contracts\SettingDefinition;
use App\Core\Contracts\SettingsRepository;
use App\Plugins\StoreSettings\src\Contracts\SettingsSection;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

final class MetaSettingsSection implements SettingsSection
{
    public function __construct(
        private readonly SettingsRepository $settings,
    ) {}

    public function pluginId(): string
    {
        return 'meta-marketing';
    }

    public function sort(): int
    {
        return 100;
    }

    public function tab(): Tab
    {
        $hasToken = filled($this->settings->get(MetaSettingKey::CAPI_ACCESS_TOKEN));

        return Tab::make('تسويق ميتا (Meta)')
            ->icon('heroicon-o-megaphone')
            ->schema([
                Section::make('بكسل ميتا (Meta Pixel)')->schema([
                    Grid::make(2)->schema([
                        TextInput::make(MetaSettingKey::PIXEL_ID->value)
                            ->label('معرف البكسل (Pixel / Dataset ID)')
                            ->helperText('من Events Manager ← مصادر البيانات. هذا ليس معرف تطبيق فيسبوك (App ID).')
                            ->regex('/^\d{5,20}$/')
                            ->maxLength(20),
                        Toggle::make(MetaSettingKey::DEBUG_MODE->value)
                            ->label('وضع التصحيح للبكسل')
                            ->helperText('يطبع كل حدث يرسله المتجر في سجل المتصفح (Console). أوقفه بعد انتهاء الاختبار.'),
                    ]),
                ]),

                Section::make('واجهة التحويلات (Conversions API)')->schema([
                    Toggle::make(MetaSettingKey::CAPI_ENABLED->value)
                        ->label('إرسال الأحداث من الخادم إلى ميتا'),
                    Grid::make(2)->schema([
                        TextInput::make(MetaSettingKey::CAPI_ACCESS_TOKEN->value)
                            ->label('رمز الوصول (Access Token)')
                            ->password()
                            ->placeholder($hasToken ? '•••••••• (محفوظ)' : null)
                            ->helperText('يُحفظ مشفراً. اتركه فارغاً للإبقاء على الرمز الحالي.')
                            // Never send the stored secret back to the browser.
                            ->formatStateUsing(fn (): ?string => null)
                            // Blank input keeps the stored token: the key is left out of the saved data.
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->dehydrateStateUsing(fn (string $state): string => Crypt::encryptString(mb_trim($state))),
                        TextInput::make(MetaSettingKey::CAPI_TEST_EVENT_CODE->value)
                            ->label('رمز أحداث الاختبار (Test Event Code)')
                            ->helperText('للاختبار فقط من Events Manager ← Test Events. احذفه بعد الاختبار.')
                            ->maxLength(50),
                    ]),
                ]),

                Section::make('كتالوج المنتجات (Meta Catalog)')->schema([
                    Toggle::make(MetaSettingKey::CATALOG_ENABLED->value)
                        ->label('تفعيل رابط تصدير الكتالوج'),
                    Grid::make(2)->schema([
                        TextInput::make(MetaSettingKey::CATALOG_FEED_TOKEN->value)
                            ->label('مفتاح رابط الكتالوج')
                            ->helperText('جزء سري من رابط الكتالوج. تغييره يوقف الرابط القديم.')
                            ->readOnly()
                            ->formatStateUsing(fn (?string $state): string => filled($state) ? $state : Str::random(40))
                            ->suffixAction(
                                Action::make('regenerateMetaCatalogToken')
                                    ->label('توليد مفتاح جديد')
                                    ->icon('heroicon-o-arrow-path')
                                    ->action(fn (Set $set) => $set(MetaSettingKey::CATALOG_FEED_TOKEN->value, Str::random(40)))
                            ),
                        TextInput::make(MetaSettingKey::CATALOG_BRAND->value)
                            ->label('العلامة التجارية في الكتالوج')
                            ->helperText('تتطلبها ميتا لكل منتج. إذا تُرك فارغاً يُستخدم اسم المتجر.')
                            ->maxLength(100),
                    ]),
                ]),
            ]);
    }

    /**
     * @return list<SettingDefinition>
     */
    public function keys(): array
    {
        return MetaSettingKey::cases();
    }
}
