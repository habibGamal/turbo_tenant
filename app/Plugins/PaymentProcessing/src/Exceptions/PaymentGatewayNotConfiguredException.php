<?php

declare(strict_types=1);

namespace App\Plugins\PaymentProcessing\src\Exceptions;

use RuntimeException;

final class PaymentGatewayNotConfiguredException extends RuntimeException {}
