<?php

namespace App\Ai\Agents;

use App\Ai\ToolRegistry;
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
    ) {}

    public function instructions(): string
    {
        return implode("\n\n", [
            $this->agentModel->description,
            "You are operating on the GitHub repository: {$this->repoFullName}.",
            'Use the available tools to interact with the repository.',
        ]);
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
        return ToolRegistry::resolve(
            $this->agentModel->tools ?? [],
            $this->repoFullName,
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
