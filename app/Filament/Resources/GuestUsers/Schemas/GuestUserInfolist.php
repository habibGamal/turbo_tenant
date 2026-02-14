<?php

declare(strict_types=1);

namespace App\Filament\Resources\GuestUsers\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class GuestUserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('معلومات الزائر')
                    ->schema([
                        TextEntry::make('name')
                            ->label('الاسم'),
                        TextEntry::make('full_phone')
                            ->label('الهاتف')
                            ->copyable(),
                        TextEntry::make('email')
                            ->label('البريد الإلكتروني')
                            ->copyable()
                            ->placeholder('—'),
                    ])
                    ->columns(2)
                    ->columnSpan(2),

                Section::make('الإحصائيات')
                    ->schema([
                        TextEntry::make('orders_count')
                            ->label('عدد الطلبات')
                            ->state(fn ($record) => $record->orders()->count())
                            ->badge()
                            ->color('success'),
                        TextEntry::make('created_at')
                            ->label('تاريخ التسجيل')
                            ->dateTime(),
                    ])
                    ->columnSpan(1),

                Section::make('العنوان')
                    ->schema([
                        TextEntry::make('city')
                            ->label('المدينة')
                            ->placeholder('—'),
                        TextEntry::make('area.name')
                            ->label('المنطقة')
                            ->placeholder('—'),
                        TextEntry::make('street')
                            ->label('الشارع')
                            ->placeholder('—'),
                        TextEntry::make('building')
                            ->label('المبنى')
                            ->placeholder('—'),
                        TextEntry::make('floor')
                            ->label('الطابق')
                            ->placeholder('—'),
                        TextEntry::make('apartment')
                            ->label('الشقة')
                            ->placeholder('—'),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),

                Section::make('الطلبات')
                    ->schema([
                        RepeatableEntry::make('orders')
                            ->schema([
                                TextEntry::make('order_number')
                                    ->label('رقم الطلب')
                                    ->copyable()
                                    ->url(fn ($record) => route('filament.admin.resources.orders.view', ['record' => $record->id]))
                                    ->color('primary'),
                                TextEntry::make('status')
                                    ->label('الحالة')
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
                                    ->label('النوع')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'web_delivery' => 'success',
                                        'web_takeaway' => 'info',
                                        'pos' => 'warning',
                                        default => 'gray',
                                    }),
                                TextEntry::make('total')
                                    ->label('الإجمالي')
                                    ->money('EGP'),
                                TextEntry::make('created_at')
                                    ->label('التاريخ')
                                    ->dateTime(),
                            ])
                            ->columns(5)
                            ->visible(fn ($record) => $record->orders()->count() > 0),
                    ])
                    ->columnSpanFull()
                    ->collapsed(fn ($record) => $record->orders()->count() > 5),
            ]);
    }
}
