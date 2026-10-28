<?php

declare(strict_types=1);

use App\Plugins\CartEngine\src\Http\Requests$n as PluginUpdateCartItemRequest;

if (! class_exists('App\Http\Requests$n', false)) {
    class_alias(PluginUpdateCartItemRequest::class, 'App\Http\Requests$n');
}
