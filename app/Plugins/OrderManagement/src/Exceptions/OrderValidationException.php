<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Exceptions;

use Exception;

class OrderValidationException extends Exception
{
    public static function withMessage(string $message): self
    {
        return new self($message);
    }
}
