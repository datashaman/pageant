<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ApiKeyValidator
{
    public function validate(string $provider, string $apiKey): bool
    {
        return match ($provider) {
            'anthropic' => $this->validateAnthropic($apiKey),
            'openai' => $this->validateOpenAi($apiKey),
            'gemini' => $this->validateGemini($apiKey),
            default => false,
        };
    }

    protected function validateAnthropic(string $apiKey): bool
    {
        try {
            $response = Http::timeout(10)->withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
            ])->get('https://api.anthropic.com/v1/models');

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    protected function validateOpenAi(string $apiKey): bool
    {
        try {
            $response = Http::timeout(10)->withToken($apiKey)
                ->get('https://api.openai.com/v1/models');

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    protected function validateGemini(string $apiKey): bool
    {
        try {
            $response = Http::timeout(10)
                ->get("https://generativelanguage.googleapis.com/v1beta/models?key={$apiKey}");

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }
}
