<?php

declare(strict_types=1);

use App\Core\Plugins\Contracts\UsersTableColumnContributor;
use App\Core\Plugins\Enums\PluginStatus;
use App\Core\Plugins\Services\PluginManager;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\TenantPlugin;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('appends contributed columns from active plugins', function () {
    $livewire = app(App\Filament\Resources\Users\Pages\ListUsers::class);
    $table = UsersTable::configure(Table::make($livewire));

    $columnNames = array_map(fn (Column $c): string => $c->getName(), $table->getColumns());

    expect($columnNames)->toContain('orders_count')
        ->and($columnNames)->toContain('name')
        ->and($columnNames)->toContain('email');
});

it('does not append contributed columns when plugin is inactive', function () {
    $tenant = tenant();
    expect($tenant)->not->toBeNull();

    TenantPlugin::query()->where('tenant_id', (string) $tenant->id)
        ->where('plugin_id', 'order-management')
        ->update(['status' => PluginStatus::DEACTIVATED]);

    app(PluginManager::class)->resetTenant($tenant);

    $livewire = app(App\Filament\Resources\Users\Pages\ListUsers::class);
    $table = UsersTable::configure(Table::make($livewire));
    $columnNames = array_map(fn (Column $c): string => $c->getName(), $table->getColumns());

    expect($columnNames)->not->toContain('orders_count')
        ->and($columnNames)->toContain('name')
        ->and($columnNames)->toContain('email');
});

it('supports adding custom contributors via extension tag', function () {
    $contributor = new class implements UsersTableColumnContributor
    {
        public function pluginId(): string
        {
            return 'store-settings';
        }

        public function columns(): array
        {
            return [
                TextColumn::make('custom_metric')->label('Custom Metric'),
            ];
        }
    };

    app()->instance('test.custom.users.contributor', $contributor);
    app()->tag(['test.custom.users.contributor'], 'admin.users.table.columns');

    $livewire = app(App\Filament\Resources\Users\Pages\ListUsers::class);
    $table = UsersTable::configure(Table::make($livewire));
    $columnNames = array_map(fn (Column $c): string => $c->getName(), $table->getColumns());

    expect($columnNames)->toContain('custom_metric');
});
