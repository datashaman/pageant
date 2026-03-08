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
     * Maximum total characters to inject into the system prompt.
     */
    public const MAX_CHARS = 4000;

    /**
     * Cache TTL in seconds (1 hour).
     */
    public const CACHE_TTL = 3600;

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
        $sections = [];
        $totalLength = 0;

        foreach (self::INSTRUCTION_FILES as $filePath) {
            $content = $this->fetchFile($installation, $repoFullName, $filePath);

            if ($content === null) {
                continue;
            }

            $section = "## Instructions from {$filePath}\n\n{$content}";
            $sectionLength = mb_strlen($section);

            if ($totalLength + $sectionLength > self::MAX_CHARS) {
                $remaining = self::MAX_CHARS - $totalLength;

                if ($remaining > 100) {
                    $sections[] = mb_substr($section, 0, $remaining - 3).'...';
                }

                break;
            }

            $sections[] = $section;
            $totalLength += $sectionLength;
        }

        if (empty($sections)) {
            return '';
        }

        return "## Repository Instructions\n\n".implode("\n\n---\n\n", $sections);
    }

    /**
     * Fetch a single file's content from the repo, with caching.
     */
    public function fetchFile(GithubInstallation $installation, string $repoFullName, string $filePath): ?string
    {
        $cacheKey = "repo_instructions:{$repoFullName}:{$filePath}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($installation, $repoFullName, $filePath) {
            return $this->fetchFileFromGitHub($installation, $repoFullName, $filePath);
        });
    }

    /**
     * Fetch file content from the GitHub Contents API. Returns null on 404.
     */
    protected function fetchFileFromGitHub(GithubInstallation $installation, string $repoFullName, string $filePath): ?string
    {
        try {
            $content = $this->github->getFileContents($installation, $repoFullName, $filePath);

            return $content;
        } catch (\Illuminate\Http\Client\RequestException $e) {
            if ($e->response->status() === 404) {
                return null;
            }

            Log::warning("Failed to fetch instruction file {$filePath} from {$repoFullName}", [
                'status' => $e->response->status(),
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
