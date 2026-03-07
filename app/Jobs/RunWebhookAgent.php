<?php

namespace App\Jobs;

use App\Ai\Agents\GitHubWebhookAgent;
use App\Models\Agent;
use App\Models\WorkItem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Laravel\Ai\Ai;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Prompts\AgentPrompt;

class RunWebhookAgent implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Agent $agent,
        public string $eventContext,
        public string $repoFullName,
        public int $installationId,
        public ?int $issueNumber = null,
    ) {}

    public function handle(ConversationStore $store): void
    {
        $workItem = $this->resolveWorkItem();

        $webhookAgent = new GitHubWebhookAgent(
            $this->agent,
            $this->repoFullName,
            $this->installationId,
            $workItem?->conversation_id,
        );

        $response = $webhookAgent->prompt($this->eventContext);

        if ($workItem) {
            if (! $workItem->conversation_id) {
                $conversationId = $store->storeConversation(null, $workItem->title);
                $workItem->update(['conversation_id' => $conversationId]);
            }

            $provider = Ai::textProviderFor($webhookAgent, $webhookAgent->provider());
            $model = $webhookAgent->model() ?? $provider->defaultTextModel();

            $prompt = new AgentPrompt($webhookAgent, $this->eventContext, [], $provider, $model);

            $store->storeUserMessage($workItem->conversation_id, null, $prompt);
            $store->storeAssistantMessage($workItem->conversation_id, null, $prompt, $response);
        }
    }

    protected function resolveWorkItem(): ?WorkItem
    {
        if (! $this->issueNumber) {
            return null;
        }

        return WorkItem::where('source', 'github')
            ->where('source_reference', "{$this->repoFullName}#{$this->issueNumber}")
            ->first();
    }
}
