<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\UserApiKey;
use Laravel\Ai\Ai;
use Laravel\Ai\AnonymousAgent;

class WebhookRelevanceFilter
{
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
        $originalKey = config("ai.providers.{$providerName}.key");

        try {
            $this->injectUserApiKey($agent, $providerName);

            $resolvedModel = $this->resolveSecondaryModel($anonymousAgent, $agent, $providerName);
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
            return [
                'relevant' => true,
                'reason' => "Relevance check failed ({$e->getMessage()}), defaulting to relevant.",
            ];
        } finally {
            config(["ai.providers.{$providerName}.key" => $originalKey]);
        }
    }

    protected function resolveSecondaryModel(AnonymousAgent $anonymousAgent, Agent $agent, string $providerName): string
    {
        $provider = Ai::textProviderFor($anonymousAgent, $providerName);

        return match ($agent->secondary_model) {
            'cheapest' => $provider->cheapestTextModel(),
            'smartest' => $provider->smartestTextModel(),
            default => $agent->secondary_model,
        };
    }

    protected function injectUserApiKey(Agent $agent, string $providerName): void
    {
        $organization = $agent->organization;

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
}
