<?php

namespace App\Services;

use App\Models\GithubInstallation;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GitHubService
{
    private const API_BASE = 'https://api.github.com';

    public function generateJwt(): string
    {
        $appId = config('services.github.app_id');
        $keyPath = config('services.github.private_key_path');
        $privateKey = file_get_contents($keyPath);

        $now = time();
        $payload = [
            'iat' => $now - 60,
            'exp' => $now + (10 * 60),
            'iss' => $appId,
        ];

        return JWT::encode($payload, $privateKey, 'RS256');
    }

    public function getInstallationToken(int $installationId): string
    {
        return Cache::remember(
            "github_installation_token_{$installationId}",
            3500,
            function () use ($installationId) {
                $jwt = $this->generateJwt();

                $response = Http::withHeaders([
                    'Authorization' => "Bearer {$jwt}",
                    'Accept' => 'application/vnd.github+json',
                ])->post(self::API_BASE."/app/installations/{$installationId}/access_tokens");

                $response->throw();

                return $response->json('token');
            }
        );
    }

    public function createIssue(GithubInstallation $installation, string $repo, array $data): array
    {
        $token = $this->getInstallationToken($installation->installation_id);

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/vnd.github+json',
        ])->post(self::API_BASE."/repos/{$repo}/issues", $data);

        $response->throw();

        return $response->json();
    }

    public function createComment(GithubInstallation $installation, string $repo, int $issueNumber, string $body): array
    {
        $token = $this->getInstallationToken($installation->installation_id);

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/vnd.github+json',
        ])->post(self::API_BASE."/repos/{$repo}/issues/{$issueNumber}/comments", [
            'body' => $body,
        ]);

        $response->throw();

        return $response->json();
    }

    public function createPullRequest(GithubInstallation $installation, string $repo, array $data): array
    {
        $token = $this->getInstallationToken($installation->installation_id);

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/vnd.github+json',
        ])->post(self::API_BASE."/repos/{$repo}/pulls", $data);

        $response->throw();

        return $response->json();
    }
}
