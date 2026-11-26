<?php

declare(strict_types=1);

namespace App\Plugins\PosIntegration\src\Filament\Actions;

use App\Core\Plugins\Services\PluginManager;
use App\Models\User;
use App\Plugins\OrderManagement\src\Enums\OrderPosStatus;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\PosIntegration\src\Models\OrderPosDelivery;
use App\Plugins\PosIntegration\src\Services\OrderDeliveryDispatcher;
use App\Plugins\PosIntegration\src\Services\OrderDeliveryEligibility;
use App\Plugins\PosIntegration\src\Services\OrderDeliveryReview;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

final class OrderDeliveryActions
{
    public static function enabled(): bool
    {
        return tenancy()->initialized && app(PluginManager::class)->isPluginActive('pos-integration');
    }

    public static function authorized(): bool
    {
        return self::enabled() && (bool) (Filament::auth()->user()?->getAttributes()['is_admin'] ?? false);
    }

    public static function send(bool $retry = false): Action
    {
        return Action::make($retry ? 'resend_to_pos' : 'send_to_pos')
            ->label(fn (?Order $record = null): string => $retry
                ? ($record?->pos_status === OrderPosStatus::RETRY_WAIT ? 'إعادة الإرسال الآن' : 'إعادة الإرسال لنقطة البيع')
                : 'إرسال لنقطة البيع')
            ->icon('heroicon-o-arrow-path')
            ->color($retry ? 'warning' : 'primary')
            ->authorize(fn (): bool => self::authorized())
            ->visible(function (Order $record) use ($retry): bool {
                if (! self::authorized()) {
                    return false;
                }

                if (! $retry) {
                    return in_array($record->pos_status, [null, OrderPosStatus::NOT_READY, OrderPosStatus::PENDING, OrderPosStatus::READY], true);
                }

                $delivery = $record->posDelivery;

                return $record->pos_status === OrderPosStatus::FAILED
                    || ($record->pos_status === OrderPosStatus::RETRY_WAIT && $delivery instanceof OrderPosDelivery && $delivery->long_retry_round > 0);
            })
            ->disabled(fn (Order $record): bool => app(OrderDeliveryEligibility::class)->blockingReason($record) !== null)
            ->tooltip(fn (Order $record): ?string => app(OrderDeliveryEligibility::class)->blockingReason($record))
            ->requiresConfirmation()
            ->modalHeading(fn (?Order $record = null): string => $retry
                ? ($record?->pos_status === OrderPosStatus::RETRY_WAIT ? 'إعادة الإرسال الآن' : 'إعادة الإرسال لنقطة البيع')
                : 'إرسال لنقطة البيع')
            ->modalDescription('سيتم إدراج الطلب في قائمة الإرسال. تأكد من عدم استلام الفرع له مسبقاً؛ يظهر تأكيد الإرسال بعد قبول نقطة البيع.')
            ->modalSubmitActionLabel('إدراج في قائمة الإرسال')
            ->action(function (Order $record, $livewire): void {
                $actor = Filament::auth()->user();
                abort_unless($actor instanceof User, 403);
                $delivery = app(OrderDeliveryDispatcher::class)->request($record, $actor)->refresh();
                $notification = Notification::make();
                match ($delivery->status) {
                    OrderPosStatus::SENT => $notification->success()->title('تم إرسال الطلب لنقطة البيع'),
                    OrderPosStatus::FAILED, OrderPosStatus::NEEDS_VERIFICATION => $notification->warning()->title('تحتاج عملية الإرسال إلى مراجعة')->body($delivery->last_error),
                    default => $notification->success()->title('تم إدراج الطلب في قائمة الإرسال'),
                };
                $notification->send();
                if (method_exists($livewire, 'refreshPosDelivery')) {
                    $livewire->refreshPosDelivery();
                }
            });
    }

    public static function verify(): Action
    {
        return Action::make('verify_pos_delivery')
            ->label('تسجيل نتيجة التحقق مع الفرع')
            ->icon('heroicon-o-clipboard-document-check')
            ->color('warning')
            ->authorize(fn (): bool => self::authorized())
            ->visible(fn (Order $record): bool => self::authorized() && app(OrderDeliveryReview::class)->canReview($record))
            ->modalHeading('تحقق من استلام الطلب في نظام الفرع')
            ->modalDescription('تواصل مع الفرع وابحث برقم الطلب قبل اختيار النتيجة. ستسجل المراجعة باسمك، وسيتم إيقاف متابعة المهام القديمة لهذا الطلب.')
            ->schema([
                Select::make('received')->label('نتيجة التحقق')->options([
                    'yes' => 'الفرع استلم الطلب بالفعل',
                    'no' => 'الفرع أكد عدم استلام الطلب',
                ])->required(),
                Textarea::make('evidence')->label('تفاصيل التحقق مع الفرع')->minLength(10)->maxLength(1000)->required(),
            ])
            ->action(function (Order $record, array $data, $livewire): void {
                $actor = Filament::auth()->user();
                abort_unless($actor instanceof User, 403);
                app(OrderDeliveryReview::class)->verify($record, $actor, $data['received'] === 'yes', $data['evidence']);
                Notification::make()->success()->title('تم تسجيل نتيجة التحقق مع الفرع')->send();
                if (method_exists($livewire, 'refreshPosDelivery')) {
                    $livewire->refreshPosDelivery();
                }
            });
    }
}
