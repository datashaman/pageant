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
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
            ])->post('https://api.anthropic.com/v1/messages', [
                'model' => 'claude-haiku-4-5-20251001',
                'max_tokens' => 1,
                'messages' => [['role' => 'user', 'content' => 'hi']],
            ]);

            return $response->status() !== 401;
        } catch (\Throwable) {
            return false;
        }
    }

    protected function validateOpenAi(string $apiKey): bool
    {
        try {
            $response = Http::withToken($apiKey)
                ->get('https://api.openai.com/v1/models');

            return $response->status() !== 401;
        } catch (\Throwable) {
            return false;
        }
    }

    protected function validateGemini(string $apiKey): bool
    {
        try {
            $response = Http::get("https://generativelanguage.googleapis.com/v1beta/models?key={$apiKey}");

            return $response->status() !== 400 && $response->status() !== 403;
        } catch (\Throwable) {
            return false;
        }
    }
}
