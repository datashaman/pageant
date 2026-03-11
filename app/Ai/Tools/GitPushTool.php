<?php

namespace App\Ai\Tools;

use App\Contracts\ExecutionDriver;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GitPushTool implements Tool
{
    public function __construct(
        protected ExecutionDriver $driver,
        protected ?User $user = null,
    ) {}

    public function description(): string
    {
        return 'Push commits to the remote repository.';
    }

    public function handle(Request $request): string
    {
        $branchResult = $this->driver->exec('git rev-parse --abbrev-ref HEAD');

        if (! $branchResult->isSuccessful()) {
            return json_encode([
                'error' => trim($branchResult->stderr) ?: 'Failed to determine current branch',
                'exit_code' => $branchResult->exitCode,
            ], JSON_PRETTY_PRINT);
        }

        $branch = trim($branchResult->stdout);

        $command = 'git push';

        if ($request['force'] ?? false) {
            $command .= ' --force-with-lease';
        }

        $trackingResult = $this->driver->exec('git config branch.'.escapeshellarg($branch).'.remote');

        if (! $trackingResult->isSuccessful() || trim($trackingResult->stdout) === '') {
            $command .= ' -u origin '.escapeshellarg($branch);
        }

        if ($this->user?->hasGithubToken()) {
            $this->configureGitCredentials();
        }

        $result = $this->driver->exec($command);

        if (! $result->isSuccessful()) {
            return json_encode([
                'error' => 'Failed to push',
                'stderr' => $result->stderr,
            ], JSON_PRETTY_PRINT);
        }

        return json_encode([
            'branch' => $branch,
            'output' => trim($result->stdout)."\n".trim($result->stderr),
        ], JSON_PRETTY_PRINT);
    }

    /**
     * Configure git to use the user's GitHub token for HTTPS push authentication.
     */
    protected function configureGitCredentials(): void
    {
        $token = $this->user->github_token;
        $username = $this->user->github_username ?? 'x-access-token';

        $remoteResult = $this->driver->exec('git remote get-url origin');

        if (! $remoteResult->isSuccessful()) {
            return;
        }

        $remoteUrl = trim($remoteResult->stdout);

        if (preg_match('#^https?://github\.com/(.+?)(?:\.git)?$#', $remoteUrl, $matches)) {
            $authenticatedUrl = "https://{$username}:{$token}@github.com/{$matches[1]}.git";
            $this->driver->exec('git remote set-url origin '.escapeshellarg($authenticatedUrl));
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'force' => $schema->boolean()
                ->description('Force push using --force-with-lease (default: false).'),
        ];
    }
}
