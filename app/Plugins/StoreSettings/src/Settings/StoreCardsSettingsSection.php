<?php

declare(strict_types=1);

namespace App\Plugins\StoreSettings\src\Settings;

use App\Core\Contracts\SettingDefinition;
use App\Enums\SettingKey;
use App\Plugins\StoreSettings\src\Contracts\SettingsSection;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Tabs\Tab;

final class StoreCardsSettingsSection implements SettingsSection
{
    public function pluginId(): string
    {
        return 'store-settings';
    }

    public function sort(): int
    {
        return 60;
    }

    public function tab(): Tab
    {
        return Tab::make('بطاقات الواجهة')
            ->icon('heroicon-o-squares-plus')
            ->schema([
                Repeater::make(SettingKey::PRODUCT_SHOW_CARDS->value)
                    ->label('')
                    ->schema([
                        TextInput::make('title')
                            ->label('عنوان البطاقة')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('description')
                            ->label('الوصف الترويجي')
                            ->required()
                            ->maxLength(255),
                        FileUpload::make('icon')
                            ->label('أيقونة / صورة البطاقة')
                            ->image()
                            ->directory('product-cards')
                            ->required(),
                    ])
                    ->columns(3)
                    ->defaultItems(0)
                    ->collapsible()
                    ->addActionLabel('إضافة بطاقة جديدة'),
            ]);
    }

    /**
     * @return list<SettingDefinition>
     */
    public function keys(): array
    {
        return [
            SettingKey::PRODUCT_SHOW_CARDS,
        ];
    }
}
