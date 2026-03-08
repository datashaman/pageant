<?php

namespace App\Console\Commands;

use App\Models\WorkItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class WorktreeCleanupCommand extends Command
{
    protected $signature = 'worktree:cleanup
        {--dry-run : List what would be cleaned without making changes}
        {--force : Clean without confirmation}';

    protected $description = 'Clean up orphaned worktree directories';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $isForce = $this->option('force');

        $cleanedCount = 0;

        $workItems = WorkItem::query()
            ->whereNotNull('worktree_path')
            ->get();

        foreach ($workItems as $workItem) {
            if (! File::isDirectory($workItem->worktree_path)) {
                $this->line("Stale reference: {$workItem->worktree_path} (directory missing)");

                if (! $isDryRun) {
                    $workItem->update([
                        'worktree_path' => null,
                        'worktree_branch' => null,
                    ]);
                    $cleanedCount++;
                }
            }
        }

        $basePath = config('execution.base_path');

        if (! File::isDirectory($basePath)) {
            $this->info('No worktree base directory found. Nothing to clean.');

            return self::SUCCESS;
        }

        $orgDirs = File::directories($basePath);

        foreach ($orgDirs as $orgDir) {
            $repoDirs = File::directories($orgDir);

            foreach ($repoDirs as $repoDir) {
                $worktreeDirs = File::directories($repoDir);

                foreach ($worktreeDirs as $worktreeDir) {
                    if (basename($worktreeDir) === '.bare') {
                        continue;
                    }

                    $workItemId = basename($worktreeDir);

                    $workItem = WorkItem::query()->find($workItemId);

                    if (! $workItem) {
                        $this->line("Orphaned worktree: {$worktreeDir} (no matching work item)");

                        if ($isDryRun) {
                            continue;
                        }

                        if (! $isForce && ! $this->confirm("Remove orphaned worktree at {$worktreeDir}?")) {
                            continue;
                        }

                        $this->removeOrphanedWorktree($worktreeDir, $repoDir);
                        $cleanedCount++;
                    }
                }
            }
        }

        if ($isDryRun) {
            $this->info('Dry run complete. No changes were made.');
        } else {
            $this->info("Cleaned up {$cleanedCount} worktree(s).");
        }

        return self::SUCCESS;
    }

    /**
     * Remove an orphaned worktree using git worktree remove, falling back to directory deletion.
     */
    protected function removeOrphanedWorktree(string $worktreeDir, string $repoDir): void
    {
        $barePath = $repoDir.'/.bare';

        if (File::isDirectory($barePath)) {
            $escapedWorktreeDir = escapeshellarg($worktreeDir);

            $result = Process::path($barePath)
                ->run("git worktree remove --force {$escapedWorktreeDir}");

            if ($result->successful()) {
                return;
            }

            $this->warn("git worktree remove failed for {$worktreeDir}: {$result->errorOutput()}");
        }

        File::deleteDirectory($worktreeDir);
    }
}
