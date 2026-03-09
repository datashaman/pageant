<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Memory Retention Days
    |--------------------------------------------------------------------------
    |
    | Memories older than this number of days will be pruned by the scheduled
    | prune command. Set to null to disable retention-based pruning.
    |
    */
    'retention_days' => (int) env('AGENT_MEMORY_RETENTION_DAYS', 90),
];
