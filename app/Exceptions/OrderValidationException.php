<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Plugins\OrderManagement\src\Exceptions\OrderValidationException as PluginOrderValidationException;

class OrderValidationException extends PluginOrderValidationException
{
}
