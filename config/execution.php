<?php

return [
    'driver' => env('EXECUTION_DRIVER', 'local'),
    'base_path' => env('EXECUTION_BASE_PATH', storage_path('worktrees')),
];
