<?php

namespace App\Jobs;

use App\Ai\Agents\GitHubWebhookAgent;
use App\Models\Agent;
use App\Models\UserApiKey;
use App\Models\WorkItem;
use App\Services\WebhookRelevanceFilter;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
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

    public function handle(ConversationStore $store, WebhookRelevanceFilter $relevanceFilter): void
    {
        $relevance = $relevanceFilter->isRelevant($this->agent, $this->eventContext, $this->repoFullName);

        if (! $relevance['relevant']) {
            Log::info('Webhook event filtered as irrelevant', [
                'agent_id' => $this->agent->id,
                'repo' => $this->repoFullName,
                'reason' => $relevance['reason'],
            ]);

            return;
        }

        $workItem = $this->resolveWorkItem();
        $conversationId = $this->resolveConversationId($workItem, $store);

        $webhookAgent = new GitHubWebhookAgent(
            $this->agent,
            $this->repoFullName,
            $conversationId,
        );

        $providerName = $webhookAgent->provider();
        $originalKey = config("ai.providers.{$providerName}.key");

        try {
            $this->injectUserApiKey($providerName);

            $provider = Ai::textProviderFor($webhookAgent, $providerName);
            $resolvedModel = match ($this->agent->model) {
                'cheapest' => $provider->cheapestTextModel(),
                'smartest' => $provider->smartestTextModel(),
                default => $webhookAgent->model() ?? $provider->defaultTextModel(),
            };

            $response = $webhookAgent->prompt($this->eventContext, model: $resolvedModel);

            if ($workItem && $conversationId) {
                $prompt = new AgentPrompt($webhookAgent, $this->eventContext, [], $provider, $resolvedModel);

                $store->storeUserMessage($conversationId, null, $prompt);
                $store->storeAssistantMessage($conversationId, null, $prompt, $response);
            }
        } finally {
            config(["ai.providers.{$providerName}.key" => $originalKey]);
        }
    }

    protected function injectUserApiKey(string $providerName): void
    {
        $organization = $this->agent->organization;

        if (! $organization) {
            return;
        }

        $owner = $organization->users()->orderBy('users.id')->first();

        if (! $owner) {
            return;
        }

        $userApiKey = UserApiKey::query()
            ->where('user_id', $owner->id)
            ->where('provider', $providerName)
            ->valid()
            ->latest('validated_at')
            ->first();

        if ($userApiKey) {
            config(["ai.providers.{$providerName}.key" => $userApiKey->api_key]);
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
