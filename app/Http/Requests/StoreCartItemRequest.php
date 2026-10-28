<?php

declare(strict_types=1);

use App\Plugins\CartEngine\src\Http\Requests$n as PluginStoreCartItemRequest;

if (! class_exists('App\Http\Requests$n', false)) {
    class_alias(PluginStoreCartItemRequest::class, 'App\Http\Requests$n');
}
