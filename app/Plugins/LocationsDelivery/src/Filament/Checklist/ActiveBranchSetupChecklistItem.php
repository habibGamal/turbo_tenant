<?php

declare(strict_types=1);

namespace App\Plugins\LocationsDelivery\src\Filament\Checklist;

use App\Core\Plugins\Contracts\SetupChecklistItem;
use App\Plugins\LocationsDelivery\src\Filament\Resources\Branches\BranchResource;
use App\Plugins\LocationsDelivery\src\Models\Branch;
use Throwable;

final class ActiveBranchSetupChecklistItem implements SetupChecklistItem
{
    public function pluginId(): string
    {
        return 'locations-delivery';
    }

    public function key(): string
    {
        return 'active_branch';
    }

    public function label(): string
    {
        return 'فرع نشط';
    }

    public function description(): string
    {
        return 'إضافة فرع نشط واحد على الأقل لاستقبال وتجهيز الطلبات';
    }

    public function isComplete(): bool
    {
        try {
            return Branch::query()->where('is_active', true)->exists();
        } catch (Throwable) {
            return false;
        }
    }

    public function url(): ?string
    {
        try {
            return BranchResource::getUrl('index');
        } catch (Throwable) {
            return null;
        }
    }

    public function sortOrder(): int
    {
        return 20;
    }
}
