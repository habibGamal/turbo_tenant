<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Settings;

use App\Core\Contracts\SettingDefinition;
use App\Plugins\StoreSettings\src\Contracts\SettingsSection;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;

final class OrderSettingsSection implements SettingsSection
{
    public function pluginId(): string
    {
        return 'order-management';
    }

    public function sort(): int
    {
        return 20;
    }

    public function tab(): Tab
    {
        return Tab::make('الطلبات')
            ->icon('heroicon-o-shopping-bag')
            ->schema([
                Section::make('الرسوم والحدود الأساسية للطلبات')->schema([
                    Grid::make(2)->schema([
                        TextInput::make(OrderSettingKey::TAX_RATE->value)
                            ->label('نسبة الضريبة المضافة (%)')
                            ->numeric()
                            ->suffix('%')
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(0),
                        TextInput::make(OrderSettingKey::MIN_ORDER_AMOUNT->value)
                            ->label('الحد الأدنى لقيمة الطلب')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->prefix('EGP'),
                    ]),
                ]),

                Section::make('التحكم في أوامر الطلبات و POS')->schema([
                    Toggle::make(OrderSettingKey::ALLOW_MANUAL_ORDER_STATUS_UPDATE->value)
                        ->label('السماح بالتعديل اليدوي لحالة الطلبات من لوحة الإدارة')
                        ->helperText('ملاحظة: افتراضياً، يتم تحديث حالات الطلبات وتتبعها عبر جهاز نقطة البيع (POS). تفعيل هذا الخيار يسمح لمدير النظام بتجاوز نظام POS وتحديث الحالة يدوياً عند الضرورة.')
                        ->default(false),
                ]),
            ]);
    }

    /**
     * @return list<SettingDefinition>
     */
    public function keys(): array
    {
        return [
            OrderSettingKey::TAX_RATE,
            OrderSettingKey::MIN_ORDER_AMOUNT,
            OrderSettingKey::ALLOW_MANUAL_ORDER_STATUS_UPDATE,
        ];
    }
}
