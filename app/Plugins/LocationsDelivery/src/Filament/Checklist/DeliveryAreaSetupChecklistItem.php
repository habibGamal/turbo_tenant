<?php

declare(strict_types=1);

namespace App\Plugins\LocationsDelivery\src\Filament\Checklist;

use App\Core\Plugins\Contracts\SetupChecklistItem;
use App\Plugins\LocationsDelivery\src\Filament\Resources\Areas\AreaResource;
use App\Plugins\LocationsDelivery\src\Models\Area;
use Throwable;

final class DeliveryAreaSetupChecklistItem implements SetupChecklistItem
{
    public function pluginId(): string
    {
        return 'locations-delivery';
    }

    public function key(): string
    {
        return 'area';
    }

    public function label(): string
    {
        return 'منطقة توصيل';
    }

    public function description(): string
    {
        return 'إضافة وتحديد منطقة توصيل واحدة على الأقل وتكلفتها';
    }

    public function isComplete(): bool
    {
        try {
            return Area::query()->exists();
        } catch (Throwable) {
            return false;
        }
    }

    public function url(): ?string
    {
        try {
            return AreaResource::getUrl('index');
        } catch (Throwable) {
            return null;
        }
    }

    public function sortOrder(): int
    {
        return 30;
    }
}
