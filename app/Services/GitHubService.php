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
        $key = config('services.github.private_key_path');
        $privateKey = str_starts_with($key, '-----BEGIN') ? $key : file_get_contents($key);

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

    /**
     * @return array<int, array{id: int, name: string, full_name: string, html_url: string, description: ?string, private: bool}>
     */
    public function listRepositories(GithubInstallation $installation, int $perPage = 100): array
    {
        $token = $this->getInstallationToken($installation->installation_id);
        $repositories = [];
        $page = 1;

        do {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$token}",
                'Accept' => 'application/vnd.github+json',
            ])->get(self::API_BASE.'/installation/repositories', [
                'per_page' => $perPage,
                'page' => $page,
            ]);

            $response->throw();

            $data = $response->json();
            $repositories = array_merge($repositories, $data['repositories'] ?? []);
            $page++;
        } while (count($repositories) < ($data['total_count'] ?? 0));

        return $repositories;
    }

    /**
     * @return array<int, array{number: int, title: string, html_url: string, state: string, labels: array, user: array, created_at: string}>
     */
    public function listIssues(GithubInstallation $installation, string $repo, int $perPage = 100): array
    {
        $token = $this->getInstallationToken($installation->installation_id);
        $issues = [];
        $page = 1;

        do {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$token}",
                'Accept' => 'application/vnd.github+json',
            ])->get(self::API_BASE."/repos/{$repo}/issues", [
                'state' => 'open',
                'per_page' => $perPage,
                'page' => $page,
            ]);

            $response->throw();

            $data = $response->json();

            if (empty($data)) {
                break;
            }

            $issues = array_merge($issues, $data);
            $page++;
        } while (count($data) === $perPage);

        // Filter out pull requests (GitHub API returns PRs as issues)
        return array_values(array_filter($issues, fn ($issue) => ! isset($issue['pull_request'])));
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
