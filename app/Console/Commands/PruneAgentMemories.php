<?php

namespace App\Console\Commands;

use App\Models\AgentMemory;
use Illuminate\Console\Command;

class PruneAgentMemories extends Command
{
    protected $signature = 'agent-memories:prune
                            {--days= : Override config retention days}';

    protected $description = 'Prune agent memories older than the configured retention period';

    public function handle(): int
    {
        $days = $this->option('days') !== null
            ? (int) $this->option('days')
            : config('agent_memory.retention_days');

        if (! $days || $days < 1) {
            $this->warn('Retention pruning is disabled. Set AGENT_MEMORY_RETENTION_DAYS or use --days.');

            return self::SUCCESS;
        }

        $cutoff = now()->subDays($days);
        $deleted = AgentMemory::query()->where('created_at', '<', $cutoff)->delete();

        $this->info("Pruned {$deleted} agent memor".($deleted === 1 ? 'y' : 'ies')." older than {$days} days.");

        return self::SUCCESS;
    }
}
