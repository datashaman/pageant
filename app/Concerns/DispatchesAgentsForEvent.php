<?php

namespace App\Concerns;

use App\Jobs\RunWebhookAgent;
use App\Models\Repo;

trait DispatchesAgentsForEvent
{
    protected function dispatchAgentsForRepo(string $repoFullName, int $installationId, string $eventName, string $eventContext, ?int $issueNumber = null): void
    {
        $repo = Repo::where('source', 'github')
            ->where('source_reference', $repoFullName)
            ->first();

        if (! $repo) {
            return;
        }

        $agents = $repo->agents()
            ->where('enabled', true)
            ->whereJsonContains('events', $eventName)
            ->get();

        foreach ($agents as $agent) {
            RunWebhookAgent::dispatch($agent, $eventContext, $repoFullName, $installationId, $issueNumber);
        }
    }
}
