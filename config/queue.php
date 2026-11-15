<?php

declare(strict_types=1);

return [
    // Laravel merges this connection with its standard queue configuration.
    // Store tenant POS jobs centrally, while retaining Stancl's tenant_id payload.
    'connections' => [
        'pos-database' => [
            'driver' => 'database',
            'connection' => env('POS_QUEUE_DB_CONNECTION', env('DB_QUEUE_CONNECTION', env('DB_CONNECTION', 'sqlite'))),
            'table' => env('DB_QUEUE_TABLE', 'jobs'),
            'queue' => env('DB_QUEUE', 'default'),
            'retry_after' => (int) env('DB_QUEUE_RETRY_AFTER', 90),
            'after_commit' => false,
        ],
    ],
];
