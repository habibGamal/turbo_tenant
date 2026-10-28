<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Filament\Resources\Orders\Schemas;

use App\Plugins\StoreSettings\src\Services\SettingService;
use BackedEnum;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $currency = fn (): string => (string) app(SettingService::class)->get('currency', 'EGP');

        return $schema
            ->columns(3)
            ->components([
                Section::make('بيانات الطلب')
                    ->schema([
                        TextEntry::make('id')
                            ->label('معرف الطلب')
                            ->copyable(),
                        TextEntry::make('order_number')
                            ->label('رقم الطلب')
                            ->copyable()
                            ->weight('bold'),
                        TextEntry::make('status')
                            ->label('حالة الطلب')
                            ->badge(),
                        TextEntry::make('type')
                            ->label('نوع الطلب')
                            ->badge(),
                        TextEntry::make('shift_id')
                            ->label('معرف الوردية')
                            ->placeholder('—'),
                        TextEntry::make('payment_status')
                            ->label('حالة الدفع')
                            ->badge(),
                        TextEntry::make('payment_method')
                            ->label('طريقة الدفع')
                            ->badge()
                            ->visible(fn ($record) => filled($record->payment_method)),
                        TextEntry::make('pos_status')
                            ->label('مزامنة POS')
                            ->badge(),
                        TextEntry::make('pos_failure_reason')
                            ->label('سبب فشل المزامنة')
                            ->visible(fn ($record) => (string) ($record->pos_status instanceof BackedEnum ? $record->pos_status->value : $record->pos_status) === 'failed')
                            ->columnSpanFull()
                            ->color('danger'),
                    ])
                    ->columns(3)
                    ->columnSpan(2),

                Section::make('بيانات العميل')
                    ->schema([
                        TextEntry::make('customer_type')
                            ->label('نوع العميل')
                            ->badge()
                            ->state(fn ($record) => $record->isGuestOrder() ? 'زائر' : 'مستخدم مسجل')
                            ->color(fn ($record) => $record->isGuestOrder() ? 'warning' : 'success'),
                        TextEntry::make('customer_name')
                            ->label('الاسم')
                            ->state(fn ($record) => $record->getCustomerName()),
                        TextEntry::make('customer_email')
                            ->label('البريد الإلكتروني')
                            ->state(fn ($record) => $record->getCustomerEmail() ?? '—')
                            ->copyable(),
                        TextEntry::make('customer_phone')
                            ->label('رقم الهاتف')
                            ->state(fn ($record) => $record->getCustomerPhone() ?? '—')
                            ->copyable(),
                        TextEntry::make('user_id')
                            ->label('معرف المستخدم')
                            ->copyable()
                            ->visible(fn ($record) => filled($record->user_id)),
                        TextEntry::make('guest_user_id')
                            ->label('معرف الزائر')
                            ->copyable()
                            ->visible(fn ($record) => filled($record->guest_user_id)),
                        TextEntry::make('address.full_address')
                            ->label('العنوان')
                            ->visible(fn ($record) => filled($record->address_id))
                            ->columnSpanFull(),
                        TextEntry::make('address.area')
                            ->label('المنطقة')
                            ->visible(fn ($record) => filled($record->address_id)),
                        TextEntry::make('guest_address')
                            ->label('عنوان الزائر')
                            ->state(fn ($record) => $record->guestUser ?
                                collect([
                                    $record->guestUser->street,
                                    $record->guestUser->building,
                                    "الدور: {$record->guestUser->floor}",
                                    "شقة: {$record->guestUser->apartment}",
                                    $record->guestUser->city,
                                    $record->guestUser->area?->name,
                                ])->filter()->join('، ') : null
                            )
                            ->visible(fn ($record) => $record->isGuestOrder() && $record->guestUser)
                            ->columnSpanFull(),
                    ])
                    ->columnSpan(1),

                Section::make('تفاصيل إضافية')
                    ->schema([
                        TextEntry::make('branch.name')
                            ->label('الفرع'),
                        TextEntry::make('branch_id')
                            ->label('معرف الفرع')
                            ->copyable(),
                        TextEntry::make('coupon_code')
                            ->label('كود الكوبون')
                            ->state(fn ($record) => $record->coupon_code ?? $record->coupon?->code)
                            ->visible(fn ($record) => filled($record->coupon_id) || filled($record->coupon_code)),
                        TextEntry::make('coupon_id')
                            ->label('معرف الكوبون')
                            ->copyable()
                            ->visible(fn ($record) => filled($record->coupon_id)),
                        TextEntry::make('address_id')
                            ->label('معرف العنوان')
                            ->copyable()
                            ->visible(fn ($record) => filled($record->address_id)),
                        TextEntry::make('note')
                            ->label('ملاحظات الطلب')
                            ->columnSpanFull()
                            ->visible(fn ($record) => filled($record->note)),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),

                Section::make('عناصر ومحتويات الطلب')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->schema([
                                Grid::make(6)->schema([
                                    TextEntry::make('product_name')
                                        ->label('المنتج')
                                        ->weight('bold'),
                                    TextEntry::make('variant_name')
                                        ->label('التنويع')
                                        ->placeholder('—'),
                                    TextEntry::make('weight_label')
                                        ->label('الوزن')
                                        ->placeholder('—')
                                        ->badge()
                                        ->color('gray')
                                        ->visible(fn ($record) => filled($record?->weight_label)),
                                    TextEntry::make('quantity')
                                        ->label('الكمية')
                                        ->numeric()
                                        ->badge()
                                        ->color('info'),
                                    TextEntry::make('unit_price')
                                        ->label('سعر الوحدة')
                                        ->money($currency),
                                    TextEntry::make('total')
                                        ->label('الإجمالي')
                                        ->weight('bold')
                                        ->money($currency),
                                ]),

                                RepeatableEntry::make('extras')
                                    ->label('الإضافات والتخصيصات المحددة')
                                    ->state(fn ($record) => $record instanceof \App\Plugins\OrderManagement\src\Models\OrderItem ? $record->extras : ($record?->extras ?? []))
                                    ->visible(fn ($record) => $record instanceof \App\Plugins\OrderManagement\src\Models\OrderItem ? $record->extras()->exists() : true)
                                    ->schema([
                                        Grid::make(3)->schema([
                                            TextEntry::make('extra_name')
                                                ->label('الإضافة')
                                                ->icon('heroicon-m-plus-circle')
                                                ->state(fn ($record) => $record->extra_name_ar ?? $record->extra_name),
                                            TextEntry::make('quantity')
                                                ->label('الكمية')
                                                ->formatStateUsing(fn ($state) => "x{$state}"),
                                            TextEntry::make('extra_price')
                                                ->label('السعر الإضافي')
                                                ->money($currency),
                                        ]),
                                    ])
                                    ->columnSpanFull(),

                                TextEntry::make('notes')
                                    ->label('ملاحظات الزبون على العنصر')
                                    ->color('warning')
                                    ->columnSpanFull()
                                    ->visible(fn ($record) => filled($record?->notes)),
                            ])
                            ->columns(1),
                    ])
                    ->columnSpanFull(),

                Section::make('ملخص الأسعار والمدفوعات')
                    ->schema([
                        TextEntry::make('sub_total')
                            ->label('المجموع الفرعي')
                            ->money($currency),
                        TextEntry::make('tax')
                            ->label('الضريبة')
                            ->money($currency),
                        TextEntry::make('service')
                            ->label('الخدمة')
                            ->money($currency),
                        TextEntry::make('delivery_fee')
                            ->label('رسوم التوصيل')
                            ->money($currency),
                        TextEntry::make('discount')
                            ->label('الخصم')
                            ->money($currency),
                        TextEntry::make('total')
                            ->label('الإجمالي النهائي')
                            ->money($currency)
                            ->weight('bold')
                            ->size('lg'),
                    ])
                    ->columns(3)
                    ->columnSpan(2),

                Section::make('التواريخ')
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('تاريخ الإنشاء')
                            ->dateTime(),
                        TextEntry::make('updated_at')
                            ->label('آخر تحديث')
                            ->dateTime(),
                    ])
                    ->columnSpan(1),

                Section::make('بيانات الدفع التقنية')
                    ->collapsed()
                    ->schema([
                        TextEntry::make('merchant_order_id')
                            ->label('معرف طلب التاجر')
                            ->copyable()
                            ->visible(fn ($record) => filled($record->merchant_order_id)),
                        TextEntry::make('transaction_id')
                            ->label('معرف المعاملة')
                            ->copyable()
                            ->visible(fn ($record) => filled($record->transaction_id)),
                        TextEntry::make('paymob_order_id')
                            ->label('معرف طلب Paymob')
                            ->copyable()
                            ->visible(fn ($record) => filled($record->paymob_order_id)),
                        TextEntry::make('payment_data')
                            ->label('بيانات بوابة الدفع')
                            ->state(fn ($record) => filled($record->payment_data) ? (is_array($record->payment_data) ? json_encode($record->payment_data, JSON_UNESCAPED_UNICODE) : (string) $record->payment_data) : '—')
                            ->copyable()
                            ->columnSpanFull()
                            ->visible(fn ($record) => filled($record->payment_data)),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
            ]);
    }
}
