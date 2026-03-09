<?php

namespace App\Jobs;

use App\Models\GithubInstallation;
use App\Models\Organization;
use App\Models\WorkItem;
use App\Services\GitHubService;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;

class ReconcileWorkItemStatuses implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public function __construct(public ?Organization $organization = null) {}

    public function uniqueId(): string
    {
        return $this->organization?->id ?? 'all';
    }

    public function handle(GitHubService $github): void
    {
        $query = WorkItem::where('source', 'github')
            ->whereNotNull('source_reference');

        if ($this->organization) {
            $query->where('organization_id', $this->organization->id);
        }

        $installations = $this->organization
            ? GithubInstallation::where('organization_id', $this->organization->id)->get()->keyBy('organization_id')
            : GithubInstallation::all()->keyBy('organization_id');

        $query->chunkById(100, function ($workItems) use ($github, $installations) {
            foreach ($workItems as $workItem) {
                if (! preg_match('/^(.+)#(\d+)$/', $workItem->source_reference, $matches)) {
                    continue;
                }

                $repoFullName = $matches[1];
                $issueNumber = (int) $matches[2];

                $installation = $installations->get($workItem->organization_id);

                if (! $installation) {
                    continue;
                }

                try {
                    $issue = $github->getIssue($installation, $repoFullName, $issueNumber);
                    $newStatus = $issue['state'] === 'open' ? 'open' : 'closed';

                    if ($workItem->status !== $newStatus) {
                        $workItem->update(['status' => $newStatus]);
                    }
                } catch (RequestException $e) {
                    if ($e->response?->status() === 404) {
                        continue;
                    }

                    Log::warning('Failed to reconcile work item status', [
                        'source_reference' => $workItem->source_reference,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });
    }
}
