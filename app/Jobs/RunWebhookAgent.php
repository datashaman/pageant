<?php

namespace App\Jobs;

use App\Ai\Agents\GitHubWebhookAgent;
use App\Models\Agent;
use App\Models\WorkItem;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Laravel\Ai\Ai;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Prompts\AgentPrompt;

class RunWebhookAgent implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public function __construct(
        public Agent $agent,
        public string $eventContext,
        public string $repoFullName,
        public ?int $issueNumber = null,
    ) {}

    public function uniqueId(): string
    {
        $key = "webhook-agent:{$this->agent->id}:{$this->repoFullName}";

        if ($this->issueNumber) {
            $key .= ":{$this->issueNumber}";
        }

        return $key;
    }

    public function handle(ConversationStore $store): void
    {
        $workItem = $this->resolveWorkItem();
        $conversationId = $this->resolveConversationId($workItem, $store);

        $webhookAgent = new GitHubWebhookAgent(
            $this->agent,
            $this->repoFullName,
            $conversationId,
        );

        $response = $webhookAgent->prompt($this->eventContext);

        if ($workItem && $conversationId) {
            $provider = Ai::textProviderFor($webhookAgent, $webhookAgent->provider());
            $model = $webhookAgent->model() ?? $provider->defaultTextModel();

            $prompt = new AgentPrompt($webhookAgent, $this->eventContext, [], $provider, $model);

            $store->storeUserMessage($conversationId, null, $prompt);
            $store->storeAssistantMessage($conversationId, null, $prompt, $response);
        }
    }

    protected function resolveConversationId(?WorkItem $workItem, ConversationStore $store): ?string
    {
        if (! $workItem) {
            return null;
        }

        if ($workItem->conversation_id) {
            return $workItem->conversation_id;
        }

        $conversationId = $store->storeConversation(null, $workItem->title);
        $workItem->update(['conversation_id' => $conversationId]);

        return $conversationId;
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
