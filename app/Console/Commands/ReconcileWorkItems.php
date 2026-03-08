<?php

namespace App\Console\Commands;

use App\Models\GithubInstallation;
use App\Models\WorkItem;
use App\Services\GitHubService;
use Illuminate\Console\Command;

class ReconcileWorkItems extends Command
{
    protected $signature = 'work-items:reconcile';

    protected $description = 'Reconcile work item statuses with their linked GitHub issues';

    public function handle(GitHubService $github): int
    {
        $query = WorkItem::where('source', 'github')
            ->whereNotNull('source_reference');

        if ($query->clone()->doesntExist()) {
            $this->info('No GitHub-linked work items found.');

            return self::SUCCESS;
        }

        $installations = GithubInstallation::all()->keyBy('organization_id');

        $synced = 0;
        $errors = 0;

        $query->chunkById(100, function ($workItems) use ($github, $installations, &$synced, &$errors) {
            foreach ($workItems as $workItem) {
                if (! preg_match('/^(.+)#(\d+)$/', $workItem->source_reference, $matches)) {
                    $this->warn("Skipping {$workItem->source_reference}: invalid format.");

                    continue;
                }

                $repoFullName = $matches[1];
                $issueNumber = (int) $matches[2];

                $installation = $installations->get($workItem->organization_id);

                if (! $installation) {
                    $this->warn("Skipping {$workItem->source_reference}: no GitHub installation found.");

                    continue;
                }

                try {
                    $issue = $github->getIssue($installation, $repoFullName, $issueNumber);
                    $newStatus = $issue['state'] === 'open' ? 'open' : 'closed';

                    if ($workItem->status !== $newStatus) {
                        $workItem->update(['status' => $newStatus]);
                        $this->line("  {$workItem->source_reference}: {$workItem->getOriginal('status')} → {$newStatus}");
                        $synced++;
                    }
                } catch (\Throwable $e) {
                    $this->error("  {$workItem->source_reference}: {$e->getMessage()}");
                    $errors++;
                }
            }
        });

        $this->info("Reconciled {$synced} work items. {$errors} errors.");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
