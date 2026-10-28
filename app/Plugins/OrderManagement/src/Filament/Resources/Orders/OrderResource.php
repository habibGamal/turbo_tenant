<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Filament\Resources\Orders;

use App\Enums\OrderStatus;
use App\Plugins\OrderManagement\src\Filament\Resources\Orders\Pages\ListOrders;
use App\Plugins\OrderManagement\src\Filament\Resources\Orders\Pages\ViewOrder;
use App\Plugins\OrderManagement\src\Filament\Resources\Orders\Schemas\OrderForm;
use App\Plugins\OrderManagement\src\Filament\Resources\Orders\Schemas\OrderInfolist;
use App\Plugins\OrderManagement\src\Filament\Resources\Orders\Tables\OrdersTable;
use App\Plugins\OrderManagement\src\Models\Order;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

final class OrderResource extends Resource
{
    use \App\Core\Plugins\Concerns\BelongsToPlugin;

    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static UnitEnum|string|null $navigationGroup = 'إدارة المبيعات';

    protected static ?string $navigationLabel = 'الطلبات';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'طلب';

    protected static ?string $pluralModelLabel = 'الطلبات';

    protected static ?string $recordTitleAttribute = 'order_number';

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'order_number',
            'customer_phone',
        ];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Order $record */
        return array_filter([
            'العميل' => $record->getCustomerName(),
            'الهاتف' => $record->getCustomerPhone(),
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return OrderForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return OrderInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrdersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = self::getModel()::where('status', OrderStatus::PENDING)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'طلبات قيد الانتظار';
    }
}
