<?php

namespace App\Services;

use App\Ai\Agents\GitHubWebhookAgent;
use App\Contracts\ExecutionDriver;
use App\Events\PlanCompleted;
use App\Events\PlanFailed;
use App\Events\PlanStepCompleted;
use App\Events\PlanStepFailed;
use App\Models\Plan;
use App\Models\PlanStep;
use App\Models\WorkItem;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Ai;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Prompts\AgentPrompt;

class WorkItemOrchestrator
{
    public function __construct(
        protected WorktreeManager $worktreeManager,
        protected ConversationStore $conversationStore,
    ) {}

    public function execute(Plan $plan): void
    {
        if (! $plan->isApproved() && $plan->status !== 'paused') {
            throw new \InvalidArgumentException('Plan must be approved before execution.');
        }

        $plan->update([
            'status' => 'running',
            'started_at' => now(),
        ]);

        $plan->load('steps.agent.skills');

        $workItem = $plan->workItem;
        $driver = $this->resolveDriver($workItem);

        try {
            foreach ($plan->steps as $step) {
                $plan->refresh();

                if ($plan->isPaused() || $plan->isCancelled()) {
                    return;
                }

                if ($step->status !== 'pending') {
                    continue;
                }

                $this->executeStep($step, $workItem, $driver);

                if ($step->isFailed()) {
                    $this->skipRemainingSteps($plan);

                    $plan->update([
                        'status' => 'failed',
                        'completed_at' => now(),
                    ]);

                    PlanFailed::dispatch($plan);

                    return;
                }
            }

            $plan->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            PlanCompleted::dispatch($plan);
        } catch (\Throwable $e) {
            Log::error('Plan execution failed', [
                'plan_id' => $plan->id,
                'error' => $e->getMessage(),
            ]);

            $this->skipRemainingSteps($plan);

            $plan->update([
                'status' => 'failed',
                'completed_at' => now(),
            ]);

            throw $e;
        }
    }

    public function cancel(Plan $plan): void
    {
        $plan->steps()
            ->where('status', 'pending')
            ->update(['status' => 'skipped']);

        $plan->update([
            'status' => 'cancelled',
            'completed_at' => now(),
        ]);
    }

    protected function skipRemainingSteps(Plan $plan): void
    {
        $plan->steps()
            ->where('status', 'pending')
            ->update(['status' => 'skipped']);
    }

    protected function executeStep(PlanStep $step, WorkItem $workItem, ?ExecutionDriver $driver): void
    {
        $step->update([
            'status' => 'running',
            'started_at' => now(),
        ]);

        $conversationId = $this->conversationStore->storeConversation(
            null,
            "Step {$step->order}: {$step->description}",
        );

        $step->update(['conversation_id' => $conversationId]);

        $priorContext = $this->buildPriorStepsContext($step);
        $prompt = implode("\n\n", array_filter([
            $priorContext,
            "## Your Task\n\n{$step->description}",
        ]));

        $repoFullName = $this->extractRepoFullName($workItem);

        $agent = new GitHubWebhookAgent(
            $step->agent,
            $repoFullName ?? '',
            $conversationId,
            $driver,
            $step,
        );

        try {
            $response = $agent->prompt($prompt);

            $provider = Ai::textProviderFor($agent, $agent->provider());
            $model = $agent->model() ?? $provider->defaultTextModel();
            $agentPrompt = new AgentPrompt($agent, $prompt, [], $provider, $model);

            $this->conversationStore->storeUserMessage($conversationId, null, $agentPrompt);
            $this->conversationStore->storeAssistantMessage($conversationId, null, $agentPrompt, $response);

            $step->update([
                'status' => 'completed',
                'completed_at' => now(),
                'result' => $this->summarizeResponse($response),
            ]);

            PlanStepCompleted::dispatch($step);
        } catch (\Throwable $e) {
            Log::error('Plan step failed', [
                'step_id' => $step->id,
                'error' => $e->getMessage(),
            ]);

            $step->update([
                'status' => 'failed',
                'completed_at' => now(),
                'result' => "Failed: {$e->getMessage()}",
            ]);

            PlanStepFailed::dispatch($step);
        }
    }

    protected const MAX_PRIOR_STEPS = 3;

    protected const PRIOR_CONTEXT_BUDGET = 2000;

    protected function buildPriorStepsContext(PlanStep $step): ?string
    {
        $priorSteps = $step->plan->steps()
            ->where('order', '<', $step->order)
            ->whereIn('status', ['completed', 'failed', 'skipped'])
            ->reorder('order', 'desc')
            ->take(static::MAX_PRIOR_STEPS)
            ->get()
            ->sortBy('order')
            ->values();

        if ($priorSteps->isEmpty()) {
            return null;
        }

        $formattedLines = [];

        foreach ($priorSteps as $prior) {
            $icon = match ($prior->status) {
                'completed' => 'DONE',
                'failed' => 'FAILED',
                'skipped' => 'SKIPPED',
                default => '?',
            };

            $result = $prior->result ? " — {$prior->result}" : '';
            $formattedLines[] = "{$prior->order}. [{$icon}] {$prior->description}{$result}";
        }

        $selected = [];
        $totalLength = 0;

        foreach (array_reverse($formattedLines) as $line) {
            if ($totalLength + strlen($line) > static::PRIOR_CONTEXT_BUDGET) {
                continue;
            }

            array_unshift($selected, $line);
            $totalLength += strlen($line);
        }

        if (empty($selected)) {
            return null;
        }

        return implode("\n", array_merge(['## Prior Steps'], $selected));
    }

    protected function resolveDriver(WorkItem $workItem): ?ExecutionDriver
    {
        if (! $workItem->worktree_path) {
            return null;
        }

        try {
            return $this->worktreeManager->createDriver($workItem);
        } catch (\Throwable $e) {
            Log::warning('Could not create execution driver for work item', [
                'work_item_id' => $workItem->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function extractRepoFullName(WorkItem $workItem): ?string
    {
        if (! $workItem->source_reference) {
            return null;
        }

        return preg_replace('/#\d+$/', '', $workItem->source_reference);
    }

    protected function summarizeResponse(mixed $response): string
    {
        $text = is_string($response) ? $response : (string) $response;

        if (strlen($text) > 200) {
            return substr($text, 0, 197).'...';
        }

        return $text;
    }
}
