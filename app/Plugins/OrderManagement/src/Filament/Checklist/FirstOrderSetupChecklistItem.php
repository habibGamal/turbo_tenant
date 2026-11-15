<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Filament\Checklist;

use App\Core\Plugins\Contracts\SetupChecklistItem;
use App\Plugins\OrderManagement\src\Filament\Resources\Orders\OrderResource;
use App\Plugins\OrderManagement\src\Models\Order;
use Throwable;

final class FirstOrderSetupChecklistItem implements SetupChecklistItem
{
    public function pluginId(): string
    {
        return 'order-management';
    }

    public function key(): string
    {
        return 'order';
    }

    public function label(): string
    {
        return 'أول طلب';
    }

    public function description(): string
    {
        return 'استقبال أول طلب تجريبي أو حقيقي من عميل';
    }

    public function isComplete(): bool
    {
        try {
            return Order::query()->exists();
        } catch (Throwable) {
            return false;
        }
    }

    public function url(): ?string
    {
        try {
            return OrderResource::getUrl('index');
        } catch (Throwable) {
            return null;
        }
    }

    public function sortOrder(): int
    {
        return 60;
    }
}
