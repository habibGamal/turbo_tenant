<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Filament\Tables;

use App\Core\Plugins\Contracts\UsersTableColumnContributor;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;

final class OrdersCountUsersTableColumnContributor implements UsersTableColumnContributor
{
    public function pluginId(): string
    {
        return 'order-management';
    }

    /**
     * @return array<Column>
     */
    public function columns(): array
    {
        return [
            TextColumn::make('orders_count')
                ->counts('orders')
                ->label('عدد الطلبات')
                ->badge()
                ->color('success')
                ->sortable()
                ->visible(fn ($livewire): bool => ($livewire->activeTab ?? 'customers') !== 'admins'),
        ];
    }
}
