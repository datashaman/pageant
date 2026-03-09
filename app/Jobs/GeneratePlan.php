<?php

namespace App\Jobs;

use App\Ai\Agents\GitHubWebhookAgent;
use App\Models\Agent;
use App\Models\Plan;
use App\Models\Repo;
use App\Models\WorkItem;
use App\Services\WorktreeManager;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GeneratePlan implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public function __construct(
        public WorkItem $workItem,
        public string $repoFullName,
    ) {}

    public function uniqueId(): string
    {
        return "generate-plan:{$this->workItem->id}";
    }

    public function handle(WorktreeManager $worktreeManager): void
    {
        if ($this->workItem->activePlan()) {
            return;
        }

        $repo = Repo::where('source', 'github')
            ->where('source_reference', $this->repoFullName)
            ->where('organization_id', $this->workItem->organization_id)
            ->first();

        if (! $repo) {
            Log::info('GeneratePlan skipped: no matching repo found', [
                'work_item_id' => $this->workItem->id,
                'repo_full_name' => $this->repoFullName,
            ]);

            return;
        }

        $agent = $this->resolvePlanningAgent($repo);

        if (! $agent) {
            Log::info('GeneratePlan skipped: no enabled agent found for repo', [
                'work_item_id' => $this->workItem->id,
                'repo_full_name' => $this->repoFullName,
            ]);

            return;
        }

        $driver = $this->provisionDriver($worktreeManager);

        if (! $driver) {
            Log::warning('GeneratePlan skipped: worktree provisioning failed', [
                'work_item_id' => $this->workItem->id,
                'repo_full_name' => $this->repoFullName,
            ]);

            return;
        }

        $webhookAgent = new GitHubWebhookAgent(
            $agent,
            $this->repoFullName,
            null,
            $driver,
        );

        try {
            $response = $webhookAgent->prompt($this->buildPrompt());
            $this->savePlan($response);
        } catch (\Throwable $e) {
            Log::error('GeneratePlan: agent execution failed', [
                'work_item_id' => $this->workItem->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function resolvePlanningAgent(Repo $repo): ?Agent
    {
        return $repo->agents()
            ->where('enabled', true)
            ->first();
    }

    protected function provisionDriver(WorktreeManager $worktreeManager): ?\App\Contracts\ExecutionDriver
    {
        try {
            return $worktreeManager->createDriver($this->workItem);
        } catch (\Throwable $e) {
            Log::warning('GeneratePlan: failed to provision worktree', [
                'work_item_id' => $this->workItem->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function buildPrompt(): string
    {
        $parts = [
            'You are researching a codebase to create an execution plan for a work item.',
            'Use the available tools to explore the codebase and understand the relevant context.',
            '',
            '## Work Item',
            "Title: {$this->workItem->title}",
            "Description: {$this->workItem->description}",
            "Source: {$this->workItem->source}",
            "Source Reference: {$this->workItem->source_reference}",
        ];

        if ($this->workItem->source_url) {
            $parts[] = "Source URL: {$this->workItem->source_url}";
        }

        $parts[] = '';
        $parts[] = '## Instructions';
        $parts[] = '1. Explore the codebase structure to understand how the project is organized.';
        $parts[] = '2. Identify the files and components relevant to this work item.';
        $parts[] = '3. Based on your research, provide a detailed plan summary that includes:';
        $parts[] = '   - What needs to be changed and why';
        $parts[] = '   - Which files and components are involved';
        $parts[] = '   - A recommended sequence of steps to implement the work item';
        $parts[] = '   - Any potential risks or considerations';
        $parts[] = '4. Be specific and actionable in your plan.';

        return implode("\n", $parts);
    }

    protected function savePlan(mixed $response): void
    {
        $this->workItem->refresh();

        if ($this->workItem->activePlan()) {
            return;
        }

        $summary = is_string($response) ? $response : (string) $response;

        Plan::create([
            'organization_id' => $this->workItem->organization_id,
            'work_item_id' => $this->workItem->id,
            'status' => 'pending',
            'summary' => $summary,
        ]);
    }
}
