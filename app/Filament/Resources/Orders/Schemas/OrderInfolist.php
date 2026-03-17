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
                        TextEntry::make('id')
                            ->label('Order ID')
                            ->copyable(),
                        TextEntry::make('order_number')
                            ->copyable(),
                        TextEntry::make('merchant_order_id')
                            ->label('Merchant Order ID')
                            ->copyable()
                            ->visible(fn ($record) => filled($record->merchant_order_id)),
                        TextEntry::make('transaction_id')
                            ->label('Transaction ID')
                            ->copyable()
                            ->visible(fn ($record) => filled($record->transaction_id)),
                        TextEntry::make('paymob_order_id')
                            ->label('Paymob Order ID')
                            ->copyable()
                            ->visible(fn ($record) => filled($record->paymob_order_id)),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string|\App\Enums\OrderStatus $state): string => match ($state instanceof \App\Enums\OrderStatus ? $state->value : $state) {
                                'pending' => 'gray',
                                'processing' => 'warning',
                                'out_for_delivery' => 'primary',
                                'completed' => 'success',
                                'cancelled' => 'danger',
                                default => 'gray',
                            }),
                        TextEntry::make('type')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'web_delivery' => 'success',
                                'web_takeaway' => 'info',
                                'pos' => 'warning',
                                default => 'gray',
                            }),
                        TextEntry::make('shift_id'),
                        TextEntry::make('payment_status')
                            ->badge()
                            ->color(fn (string|\App\Enums\PaymentStatus $state): string => match ($state instanceof \App\Enums\PaymentStatus ? $state->value : $state) {
                                'pending' => 'gray',
                                'processing' => 'warning',
                                'completed' => 'success',
                                'failed' => 'danger',
                                'refunded' => 'info',
                                default => 'gray',
                            }),
                        TextEntry::make('payment_method')
                            ->badge()
                            ->color(fn (string|\App\Enums\PaymentMethod|null $state): string => match ($state instanceof \App\Enums\PaymentMethod ? $state->value : $state) {
                                'card' => 'primary',
                                'wallet' => 'success',
                                'cod' => 'warning',
                                'kiosk' => 'info',
                                'bank_transfer' => 'gray',
                                default => 'gray',
                            })
                            ->visible(fn ($record) => filled($record->payment_method)),
                        TextEntry::make('payment_data')
                            ->label('Payment Data')
                            ->state(fn ($record) => filled($record->payment_data) ? (string) $record->payment_data : '—')
                            ->copyable()
                            ->columnSpanFull()
                            ->visible(fn ($record) => filled($record->payment_data)),
                        TextEntry::make('pos_status')
                            ->badge()
                            ->color(fn (\App\Enums\OrderPosStatus $state): string => match ($state) {
                                \App\Enums\OrderPosStatus::NOT_READY => 'gray',
                                \App\Enums\OrderPosStatus::READY => 'info',
                                \App\Enums\OrderPosStatus::SENDING => 'warning',
                                \App\Enums\OrderPosStatus::SENT => 'success',
                                \App\Enums\OrderPosStatus::FAILED => 'danger',
                            }),
                        TextEntry::make('pos_failure_reason')
                            ->visible(fn ($record) => $record->pos_status === \App\Enums\OrderPosStatus::FAILED)
                            ->columnSpanFull()
                            ->color('danger'),
                    ])
                    ->columns(3)
                    ->columnSpan(2),

                Section::make('Customer')
                    ->schema([
                        TextEntry::make('customer_type')
                            ->label('Customer Type')
                            ->badge()
                            ->state(fn ($record) => $record->isGuestOrder() ? 'Guest' : 'Registered User')
                            ->color(fn ($record) => $record->isGuestOrder() ? 'warning' : 'success'),
                        TextEntry::make('customer.name')
                            ->label('Name')
                            ->state(fn ($record) => $record->getCustomerName()),
                        TextEntry::make('customer.email')
                            ->label('Email')
                            ->state(fn ($record) => $record->getCustomerEmail() ?? '—')
                            ->copyable(),
                        TextEntry::make('customer.phone')
                            ->label('Phone')
                            ->state(fn ($record) => $record->user?->phone ?? $record->guestUser?->full_phone ?? '—')
                            ->copyable(),
                        TextEntry::make('user_id')
                            ->label('User ID')
                            ->copyable()
                            ->visible(fn ($record) => filled($record->user_id)),
                        TextEntry::make('guest_user_id')
                            ->label('Guest User ID')
                            ->copyable()
                            ->visible(fn ($record) => filled($record->guest_user_id)),
                        TextEntry::make('address.full_address')
                            ->label('Address')
                            ->visible(fn ($record) => $record->address_id)
                            ->columnSpanFull(),
                        TextEntry::make('address.area')
                            ->label('Area')
                            ->visible(fn ($record) => $record->address_id),
                        TextEntry::make('guest_address')
                            ->label('Guest Address')
                            ->state(fn ($record) => $record->guestUser ?
                                collect([
                                    $record->guestUser->street,
                                    $record->guestUser->building,
                                    "Floor: {$record->guestUser->floor}",
                                    "Apt: {$record->guestUser->apartment}",
                                    $record->guestUser->city,
                                    $record->guestUser->area?->name,
                                ])->filter()->join(', ') : null
                            )
                            ->visible(fn ($record) => $record->isGuestOrder() && $record->guestUser)
                            ->columnSpanFull(),
                    ])
                    ->columnSpan(1),

                Section::make('Order Details')
                    ->schema([
                        TextEntry::make('branch.name')
                            ->label('Branch'),
                        TextEntry::make('branch_id')
                            ->label('Branch ID')
                            ->copyable(),
                        TextEntry::make('coupon.code')
                            ->visible(fn ($record) => $record->coupon_id),
                        TextEntry::make('coupon_id')
                            ->label('Coupon ID')
                            ->copyable()
                            ->visible(fn ($record) => filled($record->coupon_id)),
                        TextEntry::make('address_id')
                            ->label('Address ID')
                            ->copyable()
                            ->visible(fn ($record) => filled($record->address_id)),
                        TextEntry::make('note')
                            ->columnSpanFull()
                            ->visible(fn ($record) => $record->note),
                    ])
                    ->columns(3)
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
                                    ->money('EGP'),
                                TextEntry::make('total')
                                    ->money('EGP')
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
                            ->money('EGP'),
                        TextEntry::make('tax')
                            ->money('EGP'),
                        TextEntry::make('service')
                            ->money('EGP'),
                        TextEntry::make('delivery_fee')
                            ->money('EGP'),
                        TextEntry::make('discount')
                            ->money('EGP'),
                        TextEntry::make('total')
                            ->money('EGP')
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
