<?php

declare(strict_types=1);

namespace App\Plugins\PosIntegration\src\Filament\Extenders;

use App\Plugins\CatalogProducts\src\Contracts\ProductFormExtender;
use App\Plugins\PosIntegration\src\Filament\Components\PosItemSelect;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Tabs\Tab;

final class PosProductFormExtender implements ProductFormExtender
{
    public function pluginId(): string
    {
        return 'pos-integration';
    }

    /**
     * @return array<Tab>
     */
    public function tabs(): array
    {
        return [
            Tab::make('ربط نقطة البيع (POS)')
                ->icon('heroicon-o-computer-desktop')
                ->schema([
                    Repeater::make('posMappings')
                        ->relationship()
                        ->schema([
                            Select::make('branch_id')
                                ->label('الفرع')
                                ->relationship('branch', 'name')
                                ->searchable()
                                ->preload()
                                ->required(),
                            Select::make('variant_id')
                                ->relationship('variant', 'name')
                                ->searchable()
                                ->preload()
                                ->label('التنويع (اختياري)'),
                            Select::make('extra_option_item_id')
                                ->relationship('extraOptionItem', 'name')
                                ->searchable()
                                ->preload()
                                ->label('عنصر الإضافة (اختياري)'),
                            PosItemSelect::make('pos_item_id')
                                ->required(),
                        ])
                        ->columns(4)
                        ->collapsible()
                        ->defaultItems(0)
                        ->addActionLabel('إضافة ربط جديد مع فرع POS'),
                ]),
        ];
    }
}
