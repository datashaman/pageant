<?php

return [
    'driver' => env('EXECUTION_DRIVER', 'local'),
    'base_path' => env('EXECUTION_BASE_PATH', storage_path('worktrees')),

    'commands' => [
        'allowed' => env('EXECUTION_ALLOWED_COMMANDS', ''),
        'denied' => env('EXECUTION_DENIED_COMMANDS', ''),
        'max_timeout' => env('EXECUTION_MAX_TIMEOUT', 300),
        'max_output_size' => env('EXECUTION_MAX_OUTPUT_SIZE', 1048576),
    ],

    'audit_retention_days' => env('EXECUTION_AUDIT_RETENTION_DAYS', 30),
];
