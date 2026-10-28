<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Allow Private Hosts
    |--------------------------------------------------------------------------
    |
    | When true, outbound requests to loopback, private, link-local,
    | or reserved addresses are allowed. Intended for local LAN dev only.
    |
    */
    'allow_private_hosts' => (bool) env('NETWORK_ALLOW_PRIVATE_HOSTS', false),
];
