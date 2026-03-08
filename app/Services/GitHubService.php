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
     * List all installations of this GitHub App using the App JWT.
     *
     * @return array<int, array{id: int, account: array{login: string, type: string}, permissions: array, events: array}>
     */
    public function listAppInstallations(): array
    {
        $jwt = $this->generateJwt();
        $installations = [];
        $page = 1;

        do {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$jwt}",
                'Accept' => 'application/vnd.github+json',
            ])->get(self::API_BASE.'/app/installations', [
                'per_page' => 100,
                'page' => $page,
            ]);

            $response->throw();

            $data = $response->json();

            if (empty($data)) {
                break;
            }

            $installations = array_merge($installations, $data);
            $page++;
        } while (count($data) === 100);

        return $installations;
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

    public function updateIssue(GithubInstallation $installation, string $repo, int $issueNumber, array $data): array
    {
        $token = $this->getInstallationToken($installation->installation_id);

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/vnd.github+json',
        ])->patch(self::API_BASE."/repos/{$repo}/issues/{$issueNumber}", $data);

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

    public function updatePullRequest(GithubInstallation $installation, string $repo, int $pullNumber, array $data): array
    {
        $token = $this->getInstallationToken($installation->installation_id);

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/vnd.github+json',
        ])->patch(self::API_BASE."/repos/{$repo}/pulls/{$pullNumber}", $data);

        $response->throw();

        return $response->json();
    }

    /**
     * @return array<int, array{id: int, name: string, color: string, description: ?string}>
     */
    public function listLabels(GithubInstallation $installation, string $repo): array
    {
        $token = $this->getInstallationToken($installation->installation_id);
        $labels = [];
        $page = 1;

        do {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$token}",
                'Accept' => 'application/vnd.github+json',
            ])->get(self::API_BASE."/repos/{$repo}/labels", [
                'per_page' => 100,
                'page' => $page,
            ]);

            $response->throw();

            $data = $response->json();

            if (empty($data)) {
                break;
            }

            $labels = array_merge($labels, $data);
            $page++;
        } while (count($data) === 100);

        return $labels;
    }

    /**
     * @return array<int, array{id: int, name: string, color: string, description: ?string}>
     */
    public function listIssueLabels(GithubInstallation $installation, string $repo, int $issueNumber): array
    {
        $token = $this->getInstallationToken($installation->installation_id);

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/vnd.github+json',
        ])->get(self::API_BASE."/repos/{$repo}/issues/{$issueNumber}/labels");

        $response->throw();

        return $response->json();
    }

    /**
     * @param  array<int, string>  $labels
     * @return array<int, array{id: int, name: string, color: string, description: ?string}>
     */
    public function addLabelsToIssue(GithubInstallation $installation, string $repo, int $issueNumber, array $labels): array
    {
        $token = $this->getInstallationToken($installation->installation_id);

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/vnd.github+json',
        ])->post(self::API_BASE."/repos/{$repo}/issues/{$issueNumber}/labels", [
            'labels' => $labels,
        ]);

        $response->throw();

        return $response->json();
    }

    public function removeLabelFromIssue(GithubInstallation $installation, string $repo, int $issueNumber, string $label): void
    {
        $token = $this->getInstallationToken($installation->installation_id);

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/vnd.github+json',
        ])->delete(self::API_BASE."/repos/{$repo}/issues/{$issueNumber}/labels/{$label}");

        $response->throw();
    }

    /**
     * @return array{id: int, name: string, color: string, description: ?string}
     */
    public function createLabel(GithubInstallation $installation, string $repo, string $name, string $color, ?string $description = null): array
    {
        $token = $this->getInstallationToken($installation->installation_id);

        $data = [
            'name' => $name,
            'color' => $color,
        ];

        if ($description !== null) {
            $data['description'] = $description;
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/vnd.github+json',
        ])->post(self::API_BASE."/repos/{$repo}/labels", $data);

        $response->throw();

        return $response->json();
    }

    public function deleteLabel(GithubInstallation $installation, string $repo, string $name): void
    {
        $token = $this->getInstallationToken($installation->installation_id);

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/vnd.github+json',
        ])->delete(self::API_BASE."/repos/{$repo}/labels/{$name}");

        $response->throw();
    }

    /**
     * @return array<int, array{name: string, commit: array{sha: string}, protected: bool}>
     */
    public function listBranches(GithubInstallation $installation, string $repo): array
    {
        $token = $this->getInstallationToken($installation->installation_id);
        $branches = [];
        $page = 1;

        do {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$token}",
                'Accept' => 'application/vnd.github+json',
            ])->get(self::API_BASE."/repos/{$repo}/branches", [
                'per_page' => 100,
                'page' => $page,
            ]);

            $response->throw();

            $data = $response->json();

            if (empty($data)) {
                break;
            }

            $branches = array_merge($branches, $data);
            $page++;
        } while (count($data) === 100);

        return $branches;
    }

    /**
     * @return array{ref: string, object: array{sha: string, type: string}}
     */
    public function createBranch(GithubInstallation $installation, string $repo, string $branchName, string $sha): array
    {
        $token = $this->getInstallationToken($installation->installation_id);

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/vnd.github+json',
        ])->post(self::API_BASE."/repos/{$repo}/git/refs", [
            'ref' => "refs/heads/{$branchName}",
            'sha' => $sha,
        ]);

        $response->throw();

        return $response->json();
    }

    public function getIssue(GithubInstallation $installation, string $repo, int $issueNumber): array
    {
        $token = $this->getInstallationToken($installation->installation_id);

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/vnd.github+json',
        ])->get(self::API_BASE."/repos/{$repo}/issues/{$issueNumber}");

        $response->throw();

        return $response->json();
    }

    public function getPullRequest(GithubInstallation $installation, string $repo, int $pullNumber): array
    {
        $token = $this->getInstallationToken($installation->installation_id);

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/vnd.github+json',
        ])->get(self::API_BASE."/repos/{$repo}/pulls/{$pullNumber}");

        $response->throw();

        return $response->json();
    }

    /**
     * @return array<int, array{number: int, title: string, state: string, head: array, base: array}>
     */
    public function listPullRequests(GithubInstallation $installation, string $repo, string $state = 'open'): array
    {
        $token = $this->getInstallationToken($installation->installation_id);
        $pullRequests = [];
        $page = 1;

        do {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$token}",
                'Accept' => 'application/vnd.github+json',
            ])->get(self::API_BASE."/repos/{$repo}/pulls", [
                'state' => $state,
                'per_page' => 100,
                'page' => $page,
            ]);

            $response->throw();

            $data = $response->json();

            if (empty($data)) {
                break;
            }

            $pullRequests = array_merge($pullRequests, $data);
            $page++;
        } while (count($data) === 100);

        return $pullRequests;
    }

    /**
     * @return array<int, array{id: int, body: string, user: array, created_at: string}>
     */
    public function listComments(GithubInstallation $installation, string $repo, int $issueNumber): array
    {
        $token = $this->getInstallationToken($installation->installation_id);
        $comments = [];
        $page = 1;

        do {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$token}",
                'Accept' => 'application/vnd.github+json',
            ])->get(self::API_BASE."/repos/{$repo}/issues/{$issueNumber}/comments", [
                'per_page' => 100,
                'page' => $page,
            ]);

            $response->throw();

            $data = $response->json();

            if (empty($data)) {
                break;
            }

            $comments = array_merge($comments, $data);
            $page++;
        } while (count($data) === 100);

        return $comments;
    }

    public function mergePullRequest(GithubInstallation $installation, string $repo, int $pullNumber, ?string $commitTitle = null, ?string $mergeMethod = null): array
    {
        $token = $this->getInstallationToken($installation->installation_id);

        $data = [];
        if ($commitTitle !== null) {
            $data['commit_title'] = $commitTitle;
        }
        if ($mergeMethod !== null) {
            $data['merge_method'] = $mergeMethod;
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/vnd.github+json',
        ])->put(self::API_BASE."/repos/{$repo}/pulls/{$pullNumber}/merge", $data);

        $response->throw();

        return $response->json();
    }

    /**
     * @return array<int, array{sha: string, filename: string, status: string, additions: int, deletions: int, changes: int}>
     */
    public function listPullRequestFiles(GithubInstallation $installation, string $repo, int $pullNumber): array
    {
        $token = $this->getInstallationToken($installation->installation_id);
        $files = [];
        $page = 1;

        do {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$token}",
                'Accept' => 'application/vnd.github+json',
            ])->get(self::API_BASE."/repos/{$repo}/pulls/{$pullNumber}/files", [
                'per_page' => 100,
                'page' => $page,
            ]);

            $response->throw();

            $data = $response->json();

            if (empty($data)) {
                break;
            }

            $files = array_merge($files, $data);
            $page++;
        } while (count($data) === 100);

        return $files;
    }

    /**
     * @param  array<int, string>  $reviewers
     * @param  array<int, string>  $teamReviewers
     */
    public function requestReviewers(GithubInstallation $installation, string $repo, int $pullNumber, array $reviewers = [], array $teamReviewers = []): array
    {
        $token = $this->getInstallationToken($installation->installation_id);

        $data = [];
        if (! empty($reviewers)) {
            $data['reviewers'] = $reviewers;
        }
        if (! empty($teamReviewers)) {
            $data['team_reviewers'] = $teamReviewers;
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/vnd.github+json',
        ])->post(self::API_BASE."/repos/{$repo}/pulls/{$pullNumber}/requested_reviewers", $data);

        $response->throw();

        return $response->json();
    }

    /**
     * @param  array<int, array{path: string, body: string, line: int, side?: string, start_line?: int, start_side?: string}>  $comments
     */
    public function createPullRequestReview(GithubInstallation $installation, string $repo, int $pullNumber, string $event, ?string $body = null, array $comments = []): array
    {
        $token = $this->getInstallationToken($installation->installation_id);

        $data = ['event' => $event];
        if ($body !== null) {
            $data['body'] = $body;
        }
        if (! empty($comments)) {
            $data['comments'] = $comments;
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/vnd.github+json',
        ])->post(self::API_BASE."/repos/{$repo}/pulls/{$pullNumber}/reviews", $data);

        $response->throw();

        return $response->json();
    }

    public function getPullRequestDiff(GithubInstallation $installation, string $repo, int $pullNumber): string
    {
        $token = $this->getInstallationToken($installation->installation_id);

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/vnd.github.diff',
        ])->get(self::API_BASE."/repos/{$repo}/pulls/{$pullNumber}");

        $response->throw();

        return $response->body();
    }

    public function getCommitStatus(GithubInstallation $installation, string $repo, string $ref): array
    {
        $token = $this->getInstallationToken($installation->installation_id);

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/vnd.github+json',
        ])->get(self::API_BASE."/repos/{$repo}/commits/{$ref}/status");

        $response->throw();

        return $response->json();
    }

    /**
     * @return array<int, array{id: int, name: string, status: string, conclusion: ?string, html_url: string}>
     */
    public function listCheckRuns(GithubInstallation $installation, string $repo, string $ref): array
    {
        $token = $this->getInstallationToken($installation->installation_id);

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/vnd.github+json',
        ])->get(self::API_BASE."/repos/{$repo}/commits/{$ref}/check-runs");

        $response->throw();

        return $response->json();
    }

    /**
     * Fetch decoded file content from a repository using the GitHub Contents API.
     *
     * Returns the UTF-8 string content for files encoded as base64.
     * Throws RequestException on non-2xx responses (including 404).
     */
    public function getFileContents(GithubInstallation $installation, string $repo, string $path): string
    {
        $token = $this->getInstallationToken($installation->installation_id);

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/vnd.github+json',
        ])->get(self::API_BASE."/repos/{$repo}/contents/{$path}");

        $response->throw();

        $data = $response->json();
        $content = $data['content'] ?? null;

        if (! is_string($content)) {
            return '';
        }

        if (($data['encoding'] ?? '') === 'base64') {
            $decoded = base64_decode($content, true);

            return $decoded === false ? '' : $decoded;
        }

        return $content;
    }

    /**
     * @return array{total_count: int, items: array}
     */
    public function searchIssues(GithubInstallation $installation, string $query): array
    {
        $token = $this->getInstallationToken($installation->installation_id);

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/vnd.github+json',
        ])->get(self::API_BASE.'/search/issues', [
            'q' => $query,
            'per_page' => 30,
        ]);

        $response->throw();

        return $response->json();
    }
}
