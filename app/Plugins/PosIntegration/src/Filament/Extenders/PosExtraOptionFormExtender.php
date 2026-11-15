<?php

declare(strict_types=1);

namespace App\Plugins\PosIntegration\src\Filament\Extenders;

use App\Plugins\CatalogProducts\src\Contracts\ExtraOptionFormExtender;
use App\Plugins\PosIntegration\src\Filament\Components\PosItemSelect;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;

final class PosExtraOptionFormExtender implements ExtraOptionFormExtender
{
    public function pluginId(): string
    {
        return 'pos-integration';
    }

    /**
     * @return array<\Filament\Schemas\Components\Component>
     */
    public function itemComponents(): array
    {
        return [
            Radio::make('pos_mapping_type')
                ->options([
                    'pos_item' => 'عنصر POS',
                    'notes' => 'ملاحظات',
                ])
                ->default('pos_item')
                ->inline()
                ->required()
                ->label('نوع ربط POS')
                ->live(),
            Repeater::make('posMappings')
                ->relationship()
                ->schema([
                    PosItemSelect::make('pos_item_id')
                        ->columnSpan(2),
                    Select::make('branch_id')
                        ->relationship('branch', 'name')
                        ->label('الفرع (اختياري)')
                        ->helperText('اتركه فارغاً لجميع الفروع'),
                ])
                ->columns(3)
                ->defaultItems(0)
                ->addActionLabel('إضافة ربط POS')
                ->collapsible()
                ->visible(fn (callable $get) => $get('pos_mapping_type') === 'pos_item')
                ->columnSpanFull(),
        ];
    }
}
