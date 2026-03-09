<?php

namespace App\Ai\Agents;

use App\Ai\ToolRegistry;
use App\Contracts\ExecutionDriver;
use App\Models\Agent;
use App\Models\PlanStep;
use App\Services\PromptAssembler;
use Laravel\Ai\Contracts\Agent as AgentContract;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;

class GitHubWebhookAgent implements AgentContract, Conversational, HasTools
{
    use Promptable;

    public function __construct(
        protected Agent $agentModel,
        protected string $repoFullName,
        protected ?string $conversationId = null,
        protected ?ExecutionDriver $driver = null,
        protected ?PlanStep $planStep = null,
    ) {}

    public function instructions(): string
    {
        $this->agentModel->loadMissing('organization');

        $activeTools = $this->resolveActiveToolNames();

        return app(PromptAssembler::class)->assemble([
            'agent' => $this->agentModel,
            'organization' => $this->agentModel->organization,
            'repoFullName' => $this->repoFullName,
            'planStep' => $this->planStep,
            'activeTools' => $activeTools,
            'worktreePath' => $this->driver?->getBasePath(),
            'worktreeBranch' => null,
        ]);
    }

    protected const MAX_CONVERSATION_MESSAGES = 20;

    /**
     * @return array<int, string>
     */
    protected function resolveActiveToolNames(): array
    {
        $this->agentModel->loadMissing('skills');

        $agentTools = $this->agentModel->tools ?? [];

        $skillTools = $this->agentModel->skills
            ->where('enabled', true)
            ->pluck('allowed_tools')
            ->flatten()
            ->filter()
            ->unique()
            ->values()
            ->all();

        return array_unique(array_merge($agentTools, $skillTools));
    }

    public function messages(): iterable
    {
        if (! $this->conversationId) {
            return [];
        }

        return resolve(ConversationStore::class)
            ->getLatestConversationMessages($this->conversationId, static::MAX_CONVERSATION_MESSAGES)
            ->all();
    }

    public function tools(): iterable
    {
        return ToolRegistry::resolve(
            $this->resolveActiveToolNames(),
            $this->repoFullName,
            driver: $this->driver,
        );
    }

    public function provider(): string
    {
        return $this->agentModel->provider;
    }

    public function model(): ?string
    {
        $model = $this->agentModel->model;

        return $model === 'inherit' ? null : $model;
    }
}
