<?php

namespace App\Concerns;

use App\Jobs\RunWebhookAgent;
use App\Models\Repo;

trait DispatchesAgentsForEvent
{
    // TODO: Webhook event text (issue body, comment body, PR description) is user-controlled
    // and passed directly to the LLM as event context. Agents with write-capable tools
    // (create_or_update_file, delete_file, merge_pull_request) could be manipulated via
    // prompt injection in these fields. Consider restricting webhook-triggered agents to
    // read-only tools, or adding human approval before executing write operations.
    protected function dispatchAgentsForRepo(string $repoFullName, string $eventName, string $eventContext, ?int $issueNumber = null): void
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
            RunWebhookAgent::dispatch($agent, $eventContext, $repoFullName, $issueNumber);
        }
    }
}
