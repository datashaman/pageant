<?php

namespace App\Services;

use App\Models\GithubInstallation;
use App\Models\Repo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RepoInstructionsService
{
    /**
     * Instruction files to look for, in priority order.
     *
     * @var array<int, string>
     */
    public const INSTRUCTION_FILES = [
        'CLAUDE.md',
        '.github/copilot-instructions.md',
        'AGENTS.md',
    ];

    /**
     * Maximum total characters for the entire injected instruction block,
     * including headers and separators.
     */
    public const MAX_CHARS = 4000;

    /**
     * Cache TTL in seconds (1 hour).
     */
    public const CACHE_TTL = 3600;

    /**
     * Sentinel value stored in cache to represent a missing file (404),
     * so we don't re-fetch on every prompt build.
     */
    private const CACHE_MISS_SENTINEL = '__REPO_INSTRUCTIONS_NOT_FOUND__';

    public function __construct(
        protected GitHubService $github,
    ) {}

    /**
     * Load and return combined repo instructions, respecting the character budget.
     */
    public function loadForRepo(string $repoFullName): string
    {
        $repo = Repo::where('source', 'github')
            ->where('source_reference', $repoFullName)
            ->first();

        if (! $repo) {
            return '';
        }

        $installation = GithubInstallation::where('organization_id', $repo->organization_id)->first();

        if (! $installation) {
            return '';
        }

        return $this->loadWithInstallation($installation, $repoFullName);
    }

    /**
     * Load instructions using an already-resolved installation.
     */
    public function loadWithInstallation(GithubInstallation $installation, string $repoFullName): string
    {
        $header = "## Repository Instructions\n\n";
        $separator = "\n\n---\n\n";
        $sections = [];
        $totalLength = mb_strlen($header);

        foreach (self::INSTRUCTION_FILES as $filePath) {
            $content = $this->fetchFile($installation, $repoFullName, $filePath);

            if ($content === null) {
                continue;
            }

            $section = "## Instructions from {$filePath}\n\n{$content}";
            $sectionLength = mb_strlen($section);
            $separatorLength = empty($sections) ? 0 : mb_strlen($separator);

            if ($totalLength + $separatorLength + $sectionLength > self::MAX_CHARS) {
                $remaining = self::MAX_CHARS - $totalLength - $separatorLength;

                if ($remaining > 100) {
                    $sections[] = mb_substr($section, 0, $remaining - 3).'...';
                }

                break;
            }

            $sections[] = $section;
            $totalLength += $separatorLength + $sectionLength;
        }

        if (empty($sections)) {
            return '';
        }

        return $header.implode($separator, $sections);
    }

    /**
     * Fetch a single file's content from the repo, with caching.
     * Returns null for missing files (cached as sentinel to avoid repeated API calls).
     */
    public function fetchFile(GithubInstallation $installation, string $repoFullName, string $filePath): ?string
    {
        $cacheKey = "repo_instructions:{$repoFullName}:{$filePath}";

        $value = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($installation, $repoFullName, $filePath) {
            return $this->fetchFileFromGitHub($installation, $repoFullName, $filePath) ?? self::CACHE_MISS_SENTINEL;
        });

        return $value === self::CACHE_MISS_SENTINEL ? null : $value;
    }

    /**
     * Fetch file content from the GitHub Contents API. Returns null on failure.
     */
    protected function fetchFileFromGitHub(GithubInstallation $installation, string $repoFullName, string $filePath): ?string
    {
        try {
            return $this->github->getFileContents($installation, $repoFullName, $filePath);
        } catch (\Illuminate\Http\Client\RequestException $e) {
            if ($e->response->status() === 404) {
                return null;
            }

            Log::warning("Failed to fetch instruction file {$filePath} from {$repoFullName}", [
                'status' => $e->response->status(),
                'error' => $e->getMessage(),
            ]);

            return null;
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning("Connection error while fetching instruction file {$filePath} from {$repoFullName}", [
                'error' => $e->getMessage(),
            ]);

            return null;
        } catch (\Throwable $e) {
            Log::error("Unexpected error while fetching instruction file {$filePath} from {$repoFullName}", [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
