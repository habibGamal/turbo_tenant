<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Order Information')
                    ->schema([
                        TextEntry::make('order_number')
                            ->copyable(),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn(string|\App\Enums\OrderStatus $state): string => match ($state instanceof \App\Enums\OrderStatus ? $state->value : $state) {
                                'pending' => 'gray',
                                'processing' => 'warning',
                                'out_for_delivery' => 'primary',
                                'completed' => 'success',
                                'cancelled' => 'danger',
                                default => 'gray',
                            }),
                        TextEntry::make('type')
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                'web_delivery' => 'success',
                                'web_takeaway' => 'info',
                                'pos' => 'warning',
                                default => 'gray',
                            }),
                        TextEntry::make('shift_id'),
                        TextEntry::make('pos_status')
                            ->badge()
                            ->color(fn(\App\Enums\OrderPosStatus $state): string => match ($state) {
                                \App\Enums\OrderPosStatus::NOT_READY => 'gray',
                                \App\Enums\OrderPosStatus::READY => 'info',
                                \App\Enums\OrderPosStatus::SENDING => 'warning',
                                \App\Enums\OrderPosStatus::SENT => 'success',
                                \App\Enums\OrderPosStatus::FAILED => 'danger',
                            }),
                        TextEntry::make('pos_failure_reason')
                            ->visible(fn($record) => $record->pos_status === \App\Enums\OrderPosStatus::FAILED)
                            ->columnSpanFull()
                            ->color('danger'),
                    ])
                    ->columns(2)
                    ->columnSpan(2),

                Section::make('Customer')
                    ->schema([
                        TextEntry::make('user.name'),
                        TextEntry::make('user.email'),
                        TextEntry::make('address.full_address')
                            ->visible(fn($record) => $record->address_id),
                        TextEntry::make('address.area')
                            ->visible(fn($record) => $record->address_id),
                    ])
                    ->columnSpan(1),

                Section::make('Order Details')
                    ->schema([
                        TextEntry::make('branch.name')
                            ->label('Branch'),
                        TextEntry::make('coupon.code')
                            ->visible(fn($record) => $record->coupon_id),
                        TextEntry::make('note')
                            ->columnSpanFull()
                            ->visible(fn($record) => $record->note),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Order Items')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->schema([
                                TextEntry::make('product_name')
                                    ->label('Product'),
                                TextEntry::make('variant_name')
                                    ->label('Variant'),
                                TextEntry::make('quantity')
                                    ->numeric(),
                                TextEntry::make('unit_price')
                                    ->money('USD'),
                                TextEntry::make('total')
                                    ->money('USD')
                                    ->weight('bold'),
                                TextEntry::make('notes')
                                    ->columnSpanFull(),
                            ])
                            ->columns(5),
                    ])
                    ->columnSpanFull(),

                Section::make('Pricing Summary')
                    ->schema([
                        TextEntry::make('sub_total')
                            ->money('USD'),
                        TextEntry::make('tax')
                            ->money('USD'),
                        TextEntry::make('service')
                            ->money('USD'),
                        TextEntry::make('delivery_fee')
                            ->money('USD'),
                        TextEntry::make('discount')
                            ->money('USD'),
                        TextEntry::make('total')
                            ->money('USD')
                            ->weight('bold')
                            ->size('lg'),
                    ])
                    ->columns(3)
                    ->columnSpan(2),

                Section::make('Dates')
                    ->schema([
                        TextEntry::make('created_at')
                            ->dateTime(),
                        TextEntry::make('updated_at')
                            ->dateTime(),
                    ])
                    ->columnSpan(1),
            ]);
    }
}
