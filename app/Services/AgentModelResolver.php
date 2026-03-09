<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\UserApiKey;
use Laravel\Ai\Ai;
use Laravel\Ai\Contracts\Agent as AgentContract;

class AgentModelResolver
{
    /**
     * Resolve the secondary model for an agent.
     */
    public function resolveSecondaryModel(AgentContract $anonymousAgent, Agent $agent, string $providerName): string
    {
        $provider = Ai::textProviderFor($anonymousAgent, $providerName);

        return match ($agent->secondary_model) {
            'cheapest' => $provider->cheapestTextModel(),
            'smartest' => $provider->smartestTextModel(),
            default => $agent->secondary_model,
        };
    }

    /**
     * Inject the organization owner's API key for the given provider.
     *
     * Returns the original key so it can be restored in a finally block.
     */
    public function injectUserApiKey(Agent $agent, string $providerName): ?string
    {
        $originalKey = config("ai.providers.{$providerName}.key");

        $organization = $agent->organization;

        if (! $organization) {
            return $originalKey;
        }

        $owner = $organization->users()->orderBy('users.id')->first();

        if (! $owner) {
            return $originalKey;
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

        return $originalKey;
    }

    /**
     * Restore the original API key after use.
     */
    public function restoreApiKey(string $providerName, ?string $originalKey): void
    {
        config(["ai.providers.{$providerName}.key" => $originalKey]);
    }
}
