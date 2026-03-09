<?php

namespace App\Ai\Agents;

use App\Ai\ToolRegistry;
use App\Contracts\ExecutionDriver;
use App\Models\Agent;
use App\Services\ConversationCompressor;
use App\Services\RepoInstructionsService;
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

    public function __construct(
        protected Agent $agentModel,
        protected string $repoFullName,
        protected ?string $conversationId = null,
        protected ?ExecutionDriver $driver = null,
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
        $parts = [
            $this->agentModel->description,
            "You are operating on the GitHub repository: {$this->repoFullName}.",
            'Use the available tools to interact with the repository.',
        ];

        $skillContexts = $this->agentModel->skills
            ->where('enabled', true)
            ->pluck('context')
            ->filter()
            ->values();

        if ($skillContexts->isNotEmpty()) {
            $parts[] = "## Skills\n\n".$skillContexts->implode("\n\n---\n\n");
        }

        $repoInstructions = $this->loadRepoInstructions();

        if ($repoInstructions !== '') {
            $parts[] = $repoInstructions;
        }

        return implode("\n\n", $parts);
    }

    protected const MAX_CONVERSATION_MESSAGES = 20;

    protected function loadRepoInstructions(): string
    {
        try {
            return app(RepoInstructionsService::class)->loadForRepo($this->repoFullName);
        } catch (\Throwable) {
            return '';
        }
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
        $agentTools = $this->agentModel->tools ?? [];

        $skillTools = $this->agentModel->skills
            ->where('enabled', true)
            ->pluck('allowed_tools')
            ->flatten()
            ->filter()
            ->unique()
            ->values()
            ->all();

        return ToolRegistry::resolve(
            array_unique(array_merge($agentTools, $skillTools)),
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
