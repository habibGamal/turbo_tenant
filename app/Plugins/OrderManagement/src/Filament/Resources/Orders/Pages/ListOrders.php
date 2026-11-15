<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Filament\Resources\Orders\Pages;

use App\Plugins\OrderManagement\src\Enums\OrderPosStatus;
use App\Plugins\OrderManagement\src\Enums\OrderStatus;
use App\Plugins\OrderManagement\src\Filament\Resources\Orders\OrderResource;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\OrderManagement\src\Services\IntegrationTokenService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

final class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    public function getDefaultActiveTab(): string|int|null
    {
        return 'all';
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $tabs = [
            'all' => Tab::make('الكل'),
            'pending' => Tab::make('قيد الانتظار')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', OrderStatus::PENDING))
                ->badge(fn () => Order::where('status', OrderStatus::PENDING)->count())
                ->badgeColor('warning'),
            'preparing' => Tab::make('قيد التحضير')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', OrderStatus::PREPARING))
                ->badge(fn () => Order::where('status', OrderStatus::PREPARING)->count())
                ->badgeColor('info'),
            'out_for_delivery' => Tab::make('خارج للتوصيل')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', OrderStatus::OUT_FOR_DELIVERY))
                ->badge(fn () => Order::where('status', OrderStatus::OUT_FOR_DELIVERY)->count())
                ->badgeColor('primary'),
            'completed' => Tab::make('مكتمل')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', OrderStatus::DELIVERED))
                ->badge(fn () => Order::where('status', OrderStatus::DELIVERED)->count())
                ->badgeColor('success'),
            'cancelled' => Tab::make('ملغي')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', OrderStatus::CANCELLED))
                ->badge(fn () => Order::where('status', OrderStatus::CANCELLED)->count())
                ->badgeColor('danger'),
        ];

        if (class_exists(OrderPosStatus::class)) {
            $tabs['pos_failed'] = Tab::make('فشل الإرسال لنقطة البيع')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('pos_status', OrderPosStatus::FAILED))
                ->badge(fn () => Order::where('pos_status', OrderPosStatus::FAILED)->count())
                ->badgeColor('danger');
        }

        return $tabs;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('integration_token')
                ->label('رمز التكامل')
                ->icon('heroicon-o-key')
                ->requiresConfirmation()
                ->modalHeading('رمز تكامل POS وتحديث حالات الطلبات')
                ->modalDescription(fn (IntegrationTokenService $service): string => 'الرمز الحالي: '.($service->masked() ?? 'لم يتم إنشاء رمز تكامل بعد')."\nعند توليد رمز جديد، سيتم إبطال الرمز السابق فوراً.")
                ->modalSubmitActionLabel('توليد رمز جديد')
                ->action(function (IntegrationTokenService $service): void {
                    $newToken = $service->generate();

                    Notification::make()
                        ->success()
                        ->title('تم توليد رمز التكامل بنجاح')
                        ->body("احفظ هذا الرمز في مكان آمن، فلن يتم عرضه مرة أخرى:\n{$newToken}")
                        ->persistent()
                        ->send();
                }),
        ];
    }
}
