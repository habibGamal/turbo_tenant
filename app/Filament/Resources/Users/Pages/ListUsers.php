<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

final class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    public function getTabs(): array
    {
        return [
            'customers' => Tab::make('العملاء')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('is_admin', false)),
            'admins' => Tab::make('المديرين')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('is_admin', true)),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'customers';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
