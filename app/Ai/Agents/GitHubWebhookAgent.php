<?php

namespace App\Ai\Agents;

use App\Ai\ToolRegistry;
use App\Contracts\ExecutionDriver;
use App\Models\Agent;
use App\Models\PlanStep;
use App\Services\ConversationCompressor;
use App\Services\PromptAssembler;
use Laravel\Ai\Contracts\Agent as AgentContract;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;

class GitHubWebhookAgent implements AgentContract, Conversational, HasTools
{
    use Promptable;

    protected ?ConversationCompressor $compressor = null;

    protected ?string $executionContext = null;

    /** @var array<int, string>|null */
    protected ?array $cachedToolNames = null;

    public function __construct(
        protected Agent $agentModel,
        protected string $repoFullName,
        protected ?string $conversationId = null,
        protected ?ExecutionDriver $driver = null,
        protected ?PlanStep $planStep = null,
    ) {}

    /**
     * Enable conversation compression for this agent.
     */
    public function withCompressor(ConversationCompressor $compressor, ?string $executionContext = null): static
    {
        $this->compressor = $compressor;
        $this->executionContext = $executionContext;

        return $this;
    }

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
        if ($this->cachedToolNames !== null) {
            return $this->cachedToolNames;
        }

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

        return $this->cachedToolNames = array_unique(array_merge($agentTools, $skillTools));
    }

    public function messages(): iterable
    {
        if (! $this->conversationId) {
            return [];
        }

        $messages = resolve(ConversationStore::class)
            ->getLatestConversationMessages($this->conversationId, static::MAX_CONVERSATION_MESSAGES)
            ->all();

        if ($this->compressor && $this->compressor->needsCompression($messages)) {
            $messages = $this->compressor->compress($messages, $this->executionContext);
        }

        return $messages;
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
        return match ($this->agentModel->model) {
            'inherit', 'cheapest', 'smartest' => null,
            default => $this->agentModel->model,
        };
    }
}
