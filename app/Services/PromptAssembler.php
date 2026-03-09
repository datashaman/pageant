<?php

namespace App\Services;

use App\Ai\ToolRegistry;
use App\Models\Agent;
use App\Models\Organization;
use App\Models\PlanStep;
use Illuminate\Support\Collection;

class PromptAssembler
{
    /**
     * Maximum retry attempts agents should make before escalating.
     */
    public const RETRY_CAP = 3;

    public function __construct(
        protected RepoInstructionsService $repoInstructionsService,
    ) {}

    /**
     * Assemble a complete system prompt from layered context sections.
     *
     * @param  array{
     *     agent: Agent,
     *     organization: Organization,
     *     repoFullName?: string|null,
     *     planStep?: PlanStep|null,
     *     activeTools?: array<int, string>,
     *     worktreePath?: string|null,
     *     worktreeBranch?: string|null,
     *     extras?: array<int, string>,
     * }  $context
     */
    public function assemble(array $context): string
    {
        $agent = $context['agent'];
        $organization = $context['organization'];
        $repoFullName = $context['repoFullName'] ?? null;
        $planStep = $context['planStep'] ?? null;
        $activeTools = $context['activeTools'] ?? [];
        $worktreePath = $context['worktreePath'] ?? null;
        $worktreeBranch = $context['worktreeBranch'] ?? null;
        $extras = $context['extras'] ?? [];

        $sections = new Collection;

        $sections->push($this->buildOrganizationPolicies($organization));
        $sections->push($this->buildRepoInstructions($repoFullName));
        $sections->push($this->buildAgentConfiguration($agent, $repoFullName));
        $sections->push($this->buildSkillInstructions($agent));
        $sections->push($this->buildExecutionContext($planStep));
        $sections->push($this->buildWorktreeContext($activeTools, $worktreePath, $worktreeBranch));
        $sections->push($this->buildBehavioralSteering());

        foreach ($extras as $extra) {
            $sections->push($extra);
        }

        return $sections
            ->filter()
            ->implode("\n\n");
    }

    /**
     * Layer 1: Organization-level policies and constraints.
     */
    protected function buildOrganizationPolicies(Organization $organization): ?string
    {
        if (empty($organization->policies)) {
            return null;
        }

        return "## Organization Policies\n\n{$organization->policies}";
    }

    /**
     * Layer 2: Repository-level instructions (CLAUDE.md, etc.).
     */
    protected function buildRepoInstructions(?string $repoFullName): ?string
    {
        if (! $repoFullName) {
            return null;
        }

        try {
            $instructions = $this->repoInstructionsService->loadForRepo($repoFullName);

            return $instructions !== '' ? $instructions : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Layer 3: Agent configuration (identity, model description, repo context).
     */
    protected function buildAgentConfiguration(Agent $agent, ?string $repoFullName): ?string
    {
        $parts = [];

        if ($agent->description) {
            $parts[] = $agent->description;
        }

        if ($repoFullName) {
            $parts[] = "You are operating on the GitHub repository: {$repoFullName}. Use the available tools to interact with the repository.";
        }

        return empty($parts) ? null : implode("\n\n", $parts);
    }

    /**
     * Layer 4: Skill instructions from enabled skills attached to the agent.
     */
    protected function buildSkillInstructions(Agent $agent): ?string
    {
        $agent->loadMissing('skills');

        $skillContexts = $agent->skills
            ->where('enabled', true)
            ->pluck('context')
            ->filter()
            ->values();

        if ($skillContexts->isEmpty()) {
            return null;
        }

        return "## Skills\n\n".$skillContexts->implode("\n\n---\n\n");
    }

    /**
     * Layer 5: Execution context (current step, plan summary, prior outcomes).
     */
    protected function buildExecutionContext(?PlanStep $planStep): ?string
    {
        if (! $planStep) {
            return null;
        }

        $planStep->loadMissing('plan.steps');
        $plan = $planStep->plan;

        $parts = [];

        if ($plan->summary) {
            $parts[] = "## Plan Summary\n\n{$plan->summary}";
        }

        $totalSteps = $plan->steps->count();
        $currentOrder = $planStep->order;
        $parts[] = "## Current Step\n\nStep {$currentOrder} of {$totalSteps}: {$planStep->description}";

        $priorSteps = $plan->steps
            ->where('order', '<', $planStep->order)
            ->whereIn('status', ['completed', 'failed', 'skipped'])
            ->sortBy('order')
            ->values();

        if ($priorSteps->isNotEmpty()) {
            $priorLines = $priorSteps->map(function (PlanStep $prior) {
                $icon = match ($prior->status) {
                    'completed' => 'DONE',
                    'failed' => 'FAILED',
                    'skipped' => 'SKIPPED',
                    default => '?',
                };

                $result = $prior->result ? " - {$prior->result}" : '';

                return "{$prior->order}. [{$icon}] {$prior->description}{$result}";
            });

            $parts[] = "## Prior Steps\n\n".$priorLines->implode("\n");
        }

        return implode("\n\n", $parts);
    }

    /**
     * Layer 6: Worktree/contextual state (only when worktree tools are active).
     */
    protected function buildWorktreeContext(array $activeTools, ?string $worktreePath, ?string $worktreeBranch): ?string
    {
        $worktreeToolNames = ToolRegistry::worktreeToolNames();

        $hasWorktreeTools = collect($activeTools)->contains(
            fn (string $tool) => in_array($tool, $worktreeToolNames)
        );

        if (! $hasWorktreeTools || ! $worktreePath) {
            return null;
        }

        $parts = ["## Worktree Context\n"];
        $parts[] = "- Working directory: {$worktreePath}";

        if ($worktreeBranch) {
            $parts[] = "- Branch: {$worktreeBranch}";
        }

        return implode("\n", $parts);
    }

    /**
     * Assemble only the repo instructions section.
     * Used by agents that manage their own prompt structure (e.g., PageantAssistant).
     */
    public function assembleRepoInstructions(?string $repoFullName): string
    {
        return $this->buildRepoInstructions($repoFullName) ?? '';
    }

    /**
     * Behavioral steering directives: retry caps, workflow guidance, autonomy.
     */
    protected function buildBehavioralSteering(): string
    {
        $retryCap = self::RETRY_CAP;

        return implode("\n", [
            '## Behavioral Directives',
            '',
            "- If a tool call fails, retry up to {$retryCap} times with adjusted parameters before reporting failure.",
            '- Work through tasks step by step. Complete each step before moving to the next.',
            '- When a step is ambiguous, make a reasonable decision and proceed rather than stopping to ask.',
            '- If you encounter an unrecoverable error, report the failure clearly with the error details and what was attempted.',
        ]);
    }
}
