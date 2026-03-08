<?php

namespace App\Console\Commands;

use App\Models\ExecutionAuditLog;
use Illuminate\Console\Command;

class AuditCleanupCommand extends Command
{
    protected $signature = 'audit:cleanup
        {--days= : Number of days to retain (default from config)}
        {--dry-run : Show what would be deleted without making changes}';

    protected $description = 'Prune execution audit log entries older than the retention period';

    public function handle(): int
    {
        $daysOption = $this->option('days');

        if ($daysOption !== null && (! ctype_digit((string) $daysOption) || (int) $daysOption < 1)) {
            $this->error('The --days option must be a positive integer (>= 1).');

            return self::FAILURE;
        }

        $days = (int) ($daysOption ?? config('execution.audit_retention_days', 30));
        $isDryRun = $this->option('dry-run');

        $cutoff = now()->subDays($days);

        $query = ExecutionAuditLog::query()->where('created_at', '<', $cutoff);

        $count = $query->count();

        if ($count === 0) {
            $this->info('No audit log entries older than '.$days.' days found.');

            return self::SUCCESS;
        }

        if ($isDryRun) {
            $this->info("Would delete {$count} audit log entries older than {$days} days.");

            return self::SUCCESS;
        }

        $query->delete();

        $this->info("Deleted {$count} audit log entries older than {$days} days.");

        return self::SUCCESS;
    }
}
