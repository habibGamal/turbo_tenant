<?php

declare(strict_types=1);

namespace App\Plugins\PosIntegration\src\Filament\Schemas;

use App\Plugins\OrderManagement\src\Enums\OrderPosStatus;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\PosIntegration\src\Filament\Actions\OrderDeliveryActions;
use App\Plugins\PosIntegration\src\Services\OrderDeliveryEligibility;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;

final class OrderDeliveryInfolist
{
    public static function make(): Section
    {
        return Section::make('متابعة الإرسال لنقطة البيع')
            ->description('حالة تسليم الطلب للفرع مستقلة عن حالة الطلب والدفع. الأعداد تشمل المحاولات المسجلة منذ بدء المتابعة.')
            ->visible(fn (): bool => OrderDeliveryActions::enabled())
            ->extraAttributes(fn (Order $record): array => in_array($record->pos_status, [OrderPosStatus::QUEUED, OrderPosStatus::SENDING, OrderPosStatus::RETRY_WAIT], true)
                ? ['wire:poll.5s' => 'refreshPosDelivery'] : [])
            ->columns(3)
            ->columnSpanFull()
            ->schema([
                TextEntry::make('delivery_status')->label('حالة الإرسال')
                    ->state(fn (Order $record) => $record->pos_status)->badge()->placeholder('لم يدرج للإرسال'),
                TextEntry::make('delivery_eligibility')->label('متطلبات الإرسال')
                    ->state(fn (Order $record): string => app(OrderDeliveryEligibility::class)->blockingReason($record) ?? 'الطلب مستوفٍ لمتطلبات الإرسال'),
                TextEntry::make('delivery_warning')->label('تنبيه المتابعة')->color('warning')
                    ->state(fn (Order $record): string => $record->posDelivery?->isOverdue()
                        ? 'تأخرت المهمة عن الوقت المتوقع. راجع عامل قائمة الانتظار وتحقق مع الفرع قبل إعادة الإرسال.'
                        : 'لم يصل تأكيد استلام الطلب. تحقق مع الفرع قبل إعادة الإرسال.')
                    ->visible(fn (Order $record): bool => $record->pos_status === OrderPosStatus::NEEDS_VERIFICATION || ($record->posDelivery?->isOverdue() ?? false)),
                TextEntry::make('delivery_history_notice')->label('تغطية السجل')
                    ->state('سجل المحاولات السابقة غير متاح؛ الأعداد المعروضة تخص المحاولات المسجلة فقط.')
                    ->visible(fn (Order $record): bool => ! ($record->posDelivery?->history_complete ?? false))->columnSpanFull(),
                TextEntry::make('delivery_attempts')->label('المحاولات المسجلة')
                    ->state(fn (Order $record) => $record->posDelivery?->attempts_count)->placeholder('غير متاح'),
                TextEntry::make('delivery_failures')->label('المحاولات الفاشلة')
                    ->state(fn (Order $record) => $record->posDelivery?->failures_count)->placeholder('غير متاح'),
                TextEntry::make('delivery_progress')->label('محاولات دورة الإرسال الحالية')
                    ->state(fn (Order $record): ?string => $record->posDelivery ? $record->posDelivery->run_attempts_count.' / 5' : null)->placeholder('غير متاح'),
                TextEntry::make('delivery_queued_at')->label('وقت الإدراج في القائمة')
                    ->state(fn (Order $record) => $record->posDelivery?->queued_at)->dateTime('Y-m-d H:i:s')->placeholder('—'),
                TextEntry::make('delivery_last_attempt')->label('آخر محاولة')
                    ->state(fn (Order $record) => $record->posDelivery?->last_attempt_at)->dateTime('Y-m-d H:i:s')->placeholder('لم تبدأ محاولة مسجلة'),
                TextEntry::make('delivery_next_retry')->label('المحاولة التالية متوقعة بعد')
                    ->state(fn (Order $record) => $record->posDelivery?->next_retry_at)->dateTime('Y-m-d H:i:s')->placeholder('—'),
                TextEntry::make('delivery_sent_at')->label('وقت تأكيد الاستلام')
                    ->state(fn (Order $record) => $record->posDelivery?->sent_at)->dateTime('Y-m-d H:i:s')->placeholder('لم يؤكد بعد'),
                TextEntry::make('delivery_requester')->label('مصدر دورة الإرسال')
                    ->state(fn (Order $record): ?string => $record->posDelivery ? ($record->posDelivery->requested_by_name ?? 'تلقائي') : null)->placeholder('غير متاح'),
                TextEntry::make('delivery_last_error')->label('آخر سبب فشل مسجل')
                    ->state(fn (Order $record) => $record->posDelivery?->last_error ?? $record->pos_failure_reason)
                    ->placeholder('لا يوجد خطأ مسجل')->columnSpanFull(),
            ]);
    }
}
