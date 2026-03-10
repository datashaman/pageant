<?php

namespace App\Concerns;

use App\Ai\EventSubscription;
use App\Jobs\RunWebhookAgent;
use App\Models\Agent;
use App\Models\WorkspaceReference;

trait DispatchesAgentsForEvent
{
    // TODO: Webhook event text (issue body, comment body, PR description) is user-controlled
    // and passed directly to the LLM as event context. Agents with write-capable tools
    // (write_file, bash, merge_pull_request) could be manipulated via prompt injection in
    // these fields. Consider restricting webhook-triggered agents to read-only tools, or
    // adding human approval before executing write operations.

    /**
     * @param  array<string, mixed>  $filterContext
     */
    protected function dispatchAgentsForRepo(
        string $repoFullName,
        string $eventType,
        ?string $action,
        array $filterContext,
        string $eventContext,
        ?int $issueNumber = null,
    ): void {
        $workspaceIds = WorkspaceReference::where('source', 'github')
            ->where('source_reference', 'LIKE', $repoFullName.'%')
            ->pluck('workspace_id');

        if ($workspaceIds->isEmpty()) {
            return;
        }

        $agents = Agent::whereHas('workspaces', fn ($q) => $q->whereIn('workspaces.id', $workspaceIds))
            ->where('enabled', true)
            ->where('events', 'like', "%{$eventType}%")
            ->get();

        foreach ($agents as $agent) {
            $subscriptions = collect($agent->events)->map(function ($entry) {
                if (is_string($entry)) {
                    return EventSubscription::fromArray(['event' => $entry, 'filters' => []]);
                }

                return EventSubscription::fromArray($entry);
            });

            $matches = $subscriptions->contains(fn (EventSubscription $sub) => $sub->matches($eventType, $action, $filterContext));

            if ($matches) {
                RunWebhookAgent::dispatch($agent, $eventContext, $repoFullName, $issueNumber);
            }
        }
    }
}
