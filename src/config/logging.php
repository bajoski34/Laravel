<?php

declare(strict_types=1);

return [
    'channels' => [
        'flutterwave' => [
            'driver' => 'single',
            'path' => storage_path('logs/flutterwave.log'),
            'level' => env('LOG_LEVEL', 'debug'),
        ],
    ],
];
