<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

final class OrderValidationException extends Exception
{
    public static function withMessage(string $message): self
    {
        return new self($message);
    }
}
