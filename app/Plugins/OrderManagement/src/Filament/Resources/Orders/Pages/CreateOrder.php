<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Filament\Resources\Orders\Pages;

use App\Plugins\OrderManagement\src\Filament\Resources\Orders\OrderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;
}
