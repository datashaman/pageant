<?php

namespace App\Services;

use App\Contracts\ExecutionDriver;
use App\Models\WorkItem;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class WorktreeManager
{
    public function __construct(
        protected string $basePath,
    ) {}

    /**
     * Provision a worktree for a work item.
     *
     * Clones the repo if needed, creates a worktree with a feature branch.
     */
    public function provision(WorkItem $workItem): string
    {
        $repoReference = $this->extractRepoReference($workItem);
        $repoSlug = $this->buildRepoSlug($repoReference);
        $barePath = $this->buildBarePath($workItem->organization_id, $repoSlug);
        $worktreePath = $this->buildWorktreePath($workItem->organization_id, $repoSlug, $workItem->id);
        $branchName = $this->buildBranchName($workItem->id);

        $this->ensureBareClone($barePath, $repoReference);
        $this->createWorktree($barePath, $worktreePath, $branchName);

        $workItem->update([
            'worktree_path' => $worktreePath,
            'worktree_branch' => $branchName,
        ]);

        return $worktreePath;
    }

    /**
     * Resolve an existing worktree path for a work item.
     */
    public function resolve(WorkItem $workItem): ?string
    {
        if ($workItem->worktree_path && File::isDirectory($workItem->worktree_path)) {
            return $workItem->worktree_path;
        }

        return null;
    }

    /**
     * Provision a new worktree or resolve an existing one.
     */
    public function provisionOrResolve(WorkItem $workItem): string
    {
        return $this->resolve($workItem) ?? $this->provision($workItem);
    }

    /**
     * Clean up a worktree for a work item.
     */
    public function cleanup(WorkItem $workItem): void
    {
        if (! $workItem->worktree_path) {
            return;
        }

        $repoReference = $this->extractRepoReference($workItem);
        $repoSlug = $this->buildRepoSlug($repoReference);
        $barePath = $this->buildBarePath($workItem->organization_id, $repoSlug);

        if (File::isDirectory($workItem->worktree_path)) {
            $escapedWorktreePath = escapeshellarg($workItem->worktree_path);

            $result = Process::path($barePath)
                ->run("git worktree remove --force {$escapedWorktreePath}");

            if (! $result->successful()) {
                Log::warning('git worktree remove failed', [
                    'work_item_id' => $workItem->id,
                    'worktree_path' => $workItem->worktree_path,
                    'error' => $result->errorOutput(),
                ]);
            }
        }

        if (File::isDirectory($workItem->worktree_path)) {
            File::deleteDirectory($workItem->worktree_path);
        }

        if ($workItem->exists) {
            $workItem->update([
                'worktree_path' => null,
                'worktree_branch' => null,
            ]);
        }
    }

    /**
     * Create an ExecutionDriver for a work item's worktree.
     */
    public function createDriver(WorkItem $workItem): ExecutionDriver
    {
        $path = $this->provisionOrResolve($workItem);

        return new LocalExecutionDriver($path);
    }

    /**
     * Extract the repo reference (e.g. "acme/widgets") from a work item's source_reference.
     */
    protected function extractRepoReference(WorkItem $workItem): string
    {
        $sourceReference = $workItem->source_reference;

        $repoReference = preg_replace('/#\d+$/', '', $sourceReference);

        if (empty($repoReference) || ! str_contains($repoReference, '/')) {
            throw new RuntimeException("Invalid source reference for worktree: {$sourceReference}");
        }

        if (str_contains($repoReference, '..') || str_contains($repoReference, "\0")) {
            throw new RuntimeException("Unsafe source reference detected: {$sourceReference}");
        }

        return $repoReference;
    }

    /**
     * Build a filesystem-safe slug from a repo reference.
     */
    protected function buildRepoSlug(string $repoReference): string
    {
        return str_replace('/', '--', $repoReference);
    }

    /**
     * Build the path for the bare clone directory.
     */
    protected function buildBarePath(string $organizationId, string $repoSlug): string
    {
        return "{$this->basePath}/{$organizationId}/{$repoSlug}/.bare";
    }

    /**
     * Build the path for a worktree directory.
     */
    protected function buildWorktreePath(string $organizationId, string $repoSlug, string $workItemId): string
    {
        return "{$this->basePath}/{$organizationId}/{$repoSlug}/{$workItemId}";
    }

    /**
     * Build the branch name for a worktree.
     */
    protected function buildBranchName(string $workItemId): string
    {
        return "pageant/{$workItemId}";
    }

    /**
     * Ensure a bare clone exists for the given repo.
     */
    protected function ensureBareClone(string $barePath, string $repoReference): void
    {
        if (File::isDirectory($barePath)) {
            $result = Process::path($barePath)
                ->run('git fetch --all');

            if (! $result->successful()) {
                throw new RuntimeException("Failed to fetch repository updates: {$result->errorOutput()}");
            }

            return;
        }

        File::ensureDirectoryExists(dirname($barePath));

        $sshUrl = "git@github.com:{$repoReference}.git";
        $escapedSshUrl = escapeshellarg($sshUrl);
        $escapedBarePath = escapeshellarg($barePath);

        $result = Process::run("git clone --bare {$escapedSshUrl} {$escapedBarePath}");

        if (! $result->successful()) {
            throw new RuntimeException("Failed to clone repository: {$result->errorOutput()}");
        }
    }

    /**
     * Create a git worktree at the specified path with a new branch.
     */
    protected function createWorktree(string $barePath, string $worktreePath, string $branchName): void
    {
        File::ensureDirectoryExists(dirname($worktreePath));

        $escapedWorktreePath = escapeshellarg($worktreePath);
        $escapedBranchName = escapeshellarg($branchName);

        $result = Process::path($barePath)
            ->run("git worktree add {$escapedWorktreePath} -b {$escapedBranchName}");

        if (! $result->successful()) {
            throw new RuntimeException("Failed to create worktree: {$result->errorOutput()}");
        }
    }
}
