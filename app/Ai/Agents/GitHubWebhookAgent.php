<?php

namespace App\Ai\Agents;

use App\Ai\ToolRegistry;
use App\Contracts\ExecutionDriver;
use App\Models\Agent;
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
    ) {}

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

        return implode("\n\n", $parts);
    }

    public function messages(): iterable
    {
        if (! $this->conversationId) {
            return [];
        }

        return resolve(ConversationStore::class)
            ->getLatestConversationMessages($this->conversationId, 100)
            ->all();
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
