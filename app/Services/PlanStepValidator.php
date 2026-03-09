<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\PlanStep;
use App\Models\UserApiKey;
use Laravel\Ai\Ai;
use Laravel\Ai\AnonymousAgent;

class PlanStepValidator
{
    /**
     * Validate a plan step's output using the agent's secondary (cheap) model.
     *
     * @return array{status: string, reason: string}
     */
    public function validate(PlanStep $step, string $stepResult, string $planContext): array
    {
        $agent = $step->agent;

        $prompt = implode("\n", [
            'You are a plan step validator. Verify that the step was completed correctly.',
            '',
            '## Plan Context',
            $planContext,
            '',
            '## Step',
            "Order: {$step->order}",
            "Description: {$step->description}",
            '',
            '## Step Output',
            $stepResult,
            '',
            'Was this step completed correctly? Reply with exactly PASSED, FAILED, or UNCERTAIN on the first line, followed by a one-line reason.',
        ]);

        $anonymousAgent = new AnonymousAgent(
            instructions: 'You are a plan step validator. Respond concisely.',
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

            $status = match (true) {
                str_starts_with($firstLine, 'PASSED') => 'passed',
                str_starts_with($firstLine, 'FAILED') => 'failed',
                default => 'uncertain',
            };

            return [
                'status' => $status,
                'reason' => $reason,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'uncertain',
                'reason' => "Validation failed ({$e->getMessage()}), skipping validation.",
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
