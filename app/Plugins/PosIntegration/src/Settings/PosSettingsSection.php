<?php

declare(strict_types=1);

namespace App\Plugins\PosIntegration\src\Settings;

use App\Core\Contracts\SettingDefinition;
use App\Plugins\StoreSettings\src\Contracts\SettingsSection;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;

final class PosSettingsSection implements SettingsSection
{
    public function pluginId(): string
    {
        return 'pos-integration';
    }

    public function sort(): int
    {
        return 40;
    }

    public function tab(): Tab
    {
        return Tab::make('نظام نقاط البيع (POS)')
            ->icon('heroicon-o-calculator')
            ->schema([
                Section::make('إعدادات الربط مع POS')->schema([
                    TextInput::make(PosSettingKey::PRODUCTS_REPO_LINK->value)
                        ->label('رابط مستودع المنتجات الرئيسي')
                        ->url()
                        ->maxLength(255)
                        ->helperText('الرابط الأساسي لمستودع المنتجات المركزي (POS Products Repository URL)'),
                    TextInput::make(PosSettingKey::PRODUCTS_REPO_API_KEY->value)
                        ->label('مفتاح API لمستودع المنتجات الرئيسي')
                        ->password()
                        ->revealable()
                        ->maxLength(255)
                        ->helperText('انسخه من نقطة البيع الرئيسية: الإعدادات › إدارة الفروع › مفتاح الربط (API) › «مفتاح API للموقع والفروع». يُرسل في ترويسة X-Api-Key'),
                ]),
            ]);
    }

    /**
     * @return list<SettingDefinition>
     */
    public function keys(): array
    {
        return [
            PosSettingKey::PRODUCTS_REPO_LINK,
            PosSettingKey::PRODUCTS_REPO_API_KEY,
        ];
    }
}
