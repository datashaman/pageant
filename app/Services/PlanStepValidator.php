<?php

namespace App\Services;

use App\Models\PlanStep;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\AnonymousAgent;

class PlanStepValidator
{
    public function __construct(
        protected AgentModelResolver $modelResolver,
    ) {}

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
        $originalKey = $this->modelResolver->injectUserApiKey($agent, $providerName);

        try {
            $resolvedModel = $this->modelResolver->resolveSecondaryModel($anonymousAgent, $agent, $providerName);
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
            Log::warning('Plan step validation failed', [
                'step_id' => $step->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'uncertain',
                'reason' => 'Validation could not be performed.',
            ];
        } finally {
            $this->modelResolver->restoreApiKey($providerName, $originalKey);
        }
    }
}
