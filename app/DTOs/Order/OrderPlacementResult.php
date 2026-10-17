<?php

declare(strict_types=1);

namespace App\DTOs\Order;

use App\Plugins\OrderManagement\src\DTOs\OrderPlacementResult as PluginOrderPlacementResult;

readonly class OrderPlacementResult extends PluginOrderPlacementResult
{
}
