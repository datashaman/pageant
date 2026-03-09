<?php

namespace App\Services;

use App\Models\Agent;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\AnonymousAgent;

class WebhookRelevanceFilter
{
    public function __construct(
        protected AgentModelResolver $modelResolver,
    ) {}

    /**
     * Pre-screen a webhook payload for relevance using the agent's secondary (cheap) model.
     *
     * @return array{relevant: bool, reason: string}
     */
    public function isRelevant(Agent $agent, string $eventContext, string $repoFullName): array
    {
        $subscribedEvents = collect($agent->events)->map(function ($event) {
            return is_string($event) ? $event : ($event['event'] ?? 'unknown');
        })->implode(', ');

        $subscribedRepos = $agent->repos->pluck('source_reference')->implode(', ');

        $prompt = implode("\n", [
            'You are a webhook relevance filter. Determine if this webhook event is relevant to the agent.',
            '',
            '## Agent',
            "Name: {$agent->name}",
            "Description: {$agent->description}",
            "Subscribed events: {$subscribedEvents}",
            "Subscribed repos: {$subscribedRepos}",
            '',
            '## Webhook Event',
            "Repository: {$repoFullName}",
            'Payload:',
            $eventContext,
            '',
            'Is this webhook event relevant to this agent? Reply with exactly YES or NO on the first line, followed by a one-line reason.',
        ]);

        $anonymousAgent = new AnonymousAgent(
            instructions: 'You are a webhook relevance filter. Respond concisely.',
            messages: [],
            tools: [],
        );

        $providerName = $agent->provider;
        $originalKey = $this->modelResolver->injectUserApiKey($agent, $providerName);

        try {
            $resolvedModel = $this->modelResolver->resolveSecondaryModel($anonymousAgent, $agent, $providerName);
            $response = $anonymousAgent->prompt($prompt, provider: $providerName, model: $resolvedModel);
            $text = trim((string) $response);

            $lines = preg_split('/\R/', $text, 2);
            $firstLine = strtoupper(trim($lines[0] ?? ''));
            $reason = trim($lines[1] ?? 'No reason provided.');

            $isRelevant = str_starts_with($firstLine, 'YES');

            return [
                'relevant' => $isRelevant,
                'reason' => $reason,
            ];
        } catch (\Throwable $e) {
            Log::warning('Webhook relevance check failed, defaulting to relevant', [
                'agent_id' => $agent->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'relevant' => true,
                'reason' => 'Relevance check could not be performed, defaulting to relevant.',
            ];
        } finally {
            $this->modelResolver->restoreApiKey($providerName, $originalKey);
        }
    }
}
