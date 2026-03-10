<?php

namespace App\Services;

use App\Models\AgentMemory;
use App\Models\Plan;
use Illuminate\Support\Collection;

class AgentMemoryService
{
    protected const TOKEN_BUDGET = 500;

    protected const CHARS_PER_TOKEN = 4;

    protected const MAX_MEMORIES = 10;

    /**
     * Store a learning from a completed or failed plan execution.
     */
    public function storeFromPlan(Plan $plan): AgentMemory
    {
        $plan->load('workspace', 'steps');

        $isSuccess = $plan->status === 'completed';
        $type = $isSuccess ? 'learning' : 'failure';

        $stepSummaries = $plan->steps
            ->map(fn ($step) => "[{$step->status}] {$step->description}")
            ->implode("\n");

        $workspaceName = $plan->workspace?->name ?? 'Unknown';

        $content = implode("\n\n", array_filter([
            "Workspace: {$workspaceName}",
            "Plan Status: {$plan->status}",
            "Steps:\n{$stepSummaries}",
        ]));

        $summary = $isSuccess
            ? "Successfully completed: {$workspaceName}"
            : "Failed: {$workspaceName}";

        if (mb_strlen($summary) > 255) {
            $summary = mb_substr($summary, 0, 252).'...';
        }

        $importance = $this->calculateImportance($plan);

        return AgentMemory::create([
            'organization_id' => $plan->organization_id,
            'workspace_id' => $plan->workspace_id,
            'type' => $type,
            'content' => $content,
            'summary' => $summary,
            'importance' => $importance,
            'metadata' => [
                'plan_id' => $plan->id,
                'workspace_id' => $plan->workspace_id,
                'step_count' => $plan->steps->count(),
            ],
        ]);
    }

    /**
     * Retrieve relevant memories for a given organization and optional workspace,
     * scored by composite of recency and importance.
     *
     * @return Collection<int, AgentMemory>
     */
    public function retrieve(string $organizationId, ?string $workspaceId = null, ?string $type = null): Collection
    {
        $query = AgentMemory::query()
            ->where('organization_id', $organizationId);

        if ($workspaceId) {
            $query->where(function ($q) use ($workspaceId) {
                $q->where('workspace_id', $workspaceId)
                    ->orWhereNull('workspace_id');
            });
        }

        if ($type) {
            $query->where('type', $type);
        }

        $memories = $query
            ->latest()
            ->take(static::MAX_MEMORIES * 2)
            ->get();

        return $this->scoreAndTruncate($memories);
    }

    /**
     * Build a context string from retrieved memories suitable for inclusion in agent prompts.
     */
    public function buildContext(string $organizationId, ?string $workspaceId = null): ?string
    {
        $memories = $this->retrieve($organizationId, $workspaceId);

        if ($memories->isEmpty()) {
            return null;
        }

        $lines = $memories->map(function (AgentMemory $memory) {
            $typeLabel = strtoupper($memory->type);

            return "[{$typeLabel}] {$memory->summary}";
        });

        return "## Prior Learnings\n\n".implode("\n", $lines->all());
    }

    /**
     * Score memories using composite of recency and importance, then truncate to token budget.
     *
     * @param  Collection<int, AgentMemory>  $memories
     * @return Collection<int, AgentMemory>
     */
    protected function scoreAndTruncate(Collection $memories): Collection
    {
        if ($memories->isEmpty()) {
            return $memories;
        }

        $now = now();

        $scored = $memories->map(function (AgentMemory $memory) use ($now) {
            $ageInDays = max(1, $now->diffInDays($memory->created_at));
            $recencyScore = 1 / log($ageInDays + 1, 2);
            $importanceScore = $memory->importance / 10;

            $memory->composite_score = ($recencyScore * 0.4) + ($importanceScore * 0.6);

            return $memory;
        });

        $sorted = $scored->sortByDesc('composite_score')->values();

        $charBudget = static::TOKEN_BUDGET * static::CHARS_PER_TOKEN;
        $totalChars = 0;
        $selected = [];

        foreach ($sorted as $memory) {
            $length = mb_strlen($memory->summary);

            if ($totalChars + $length > $charBudget) {
                continue;
            }

            $selected[] = $memory;
            $totalChars += $length;

            if (count($selected) >= static::MAX_MEMORIES) {
                break;
            }
        }

        return collect($selected);
    }

    /**
     * Calculate importance based on plan outcome and complexity.
     */
    protected function calculateImportance(Plan $plan): int
    {
        $base = $plan->status === 'completed' ? 5 : 7;

        $stepCount = $plan->steps->count();
        if ($stepCount > 5) {
            $base = min(10, $base + 1);
        }

        $failedSteps = $plan->steps->where('status', 'failed')->count();
        if ($failedSteps > 0) {
            $base = min(10, $base + $failedSteps);
        }

        return min(10, max(1, $base));
    }
}
