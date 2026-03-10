<?php

namespace App\Services;

use App\Models\WorkItem;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class WorktreeBrowser
{
    /**
     * Get the git diff between the worktree branch and the base branch.
     *
     * @return array{diff: string, stats: array{files_changed: int, insertions: int, deletions: int}}
     */
    public function getDiff(WorkItem $workItem, string $mode = 'local'): array
    {
        $worktreePath = $workItem->worktree_path;

        if (! $worktreePath || ! File::isDirectory($worktreePath)) {
            return ['diff' => '', 'stats' => ['files_changed' => 0, 'insertions' => 0, 'deletions' => 0]];
        }

        $baseBranch = $this->resolveBaseBranch($worktreePath);

        if ($mode === 'base') {
            $diffCommand = "git diff {$baseBranch}...HEAD";
        } else {
            $diffCommand = 'git diff HEAD';
        }

        $result = Process::path($worktreePath)->run($diffCommand);
        $diff = $result->successful() ? $result->output() : '';

        $statsResult = Process::path($worktreePath)->run("{$diffCommand} --stat");
        $stats = $this->parseDiffStats($statsResult->successful() ? $statsResult->output() : '');

        return ['diff' => $diff, 'stats' => $stats];
    }

    /**
     * Get the list of changed files between the worktree branch and the base branch.
     *
     * @return array<int, array{path: string, status: string}>
     */
    public function getChangedFiles(WorkItem $workItem): array
    {
        $worktreePath = $workItem->worktree_path;

        if (! $worktreePath || ! File::isDirectory($worktreePath)) {
            return [];
        }

        $baseBranch = $this->resolveBaseBranch($worktreePath);

        $result = Process::path($worktreePath)->run("git diff --name-status {$baseBranch}...HEAD");

        if (! $result->successful()) {
            return [];
        }

        $files = [];

        foreach (explode("\n", trim($result->output())) as $line) {
            if (empty($line)) {
                continue;
            }

            $parts = explode("\t", $line);

            if (count($parts) >= 2) {
                $statusCode = $parts[0];
                $status = match (substr($statusCode, 0, 1)) {
                    'A' => 'added',
                    'M' => 'modified',
                    'D' => 'deleted',
                    'R' => 'renamed',
                    'C' => 'copied',
                    default => 'modified',
                };

                $path = (substr($statusCode, 0, 1) === 'R' || substr($statusCode, 0, 1) === 'C')
                    ? end($parts)
                    : $parts[1];

                $files[] = ['path' => $path, 'status' => $status];
            }
        }

        return $files;
    }

    /**
     * Get the file tree of the worktree repository.
     *
     * @return array<int, array{path: string, type: string, name: string}>
     */
    public function getFileTree(WorkItem $workItem, string $directory = ''): array
    {
        $worktreePath = $workItem->worktree_path;

        if (! $worktreePath || ! File::isDirectory($worktreePath)) {
            return [];
        }

        $targetPath = $directory
            ? rtrim($worktreePath, '/').'/'.$directory
            : $worktreePath;

        if (! File::isDirectory($targetPath)) {
            return [];
        }

        $command = 'git ls-tree --name-only HEAD';
        if ($directory !== '') {
            $command .= ' '.escapeshellarg(rtrim($directory, '/').'/');
        }

        $result = Process::path($worktreePath)->run($command);

        if (! $result->successful()) {
            return [];
        }

        $entries = [];

        foreach (explode("\n", trim($result->output())) as $line) {
            if (empty($line)) {
                continue;
            }

            $name = basename($line);
            $fullPath = $worktreePath.'/'.$line;
            $type = File::isDirectory($fullPath) ? 'directory' : 'file';

            $entries[] = [
                'path' => $line,
                'type' => $type,
                'name' => $name,
            ];
        }

        usort($entries, function (array $a, array $b): int {
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'directory' ? -1 : 1;
            }

            return strcasecmp($a['name'], $b['name']);
        });

        return $entries;
    }

    /**
     * Get the contents of a file in the worktree.
     */
    /**
     * Maximum file size in bytes that can be read (1 MB).
     */
    protected const MAX_FILE_SIZE = 1_048_576;

    public function getFileContents(WorkItem $workItem, string $filePath): ?string
    {
        $worktreePath = $workItem->worktree_path;

        if (! $worktreePath || ! File::isDirectory($worktreePath)) {
            return null;
        }

        $rootPath = realpath($worktreePath);
        if ($rootPath === false) {
            return null;
        }

        $fullPath = realpath($rootPath.DIRECTORY_SEPARATOR.ltrim($filePath, DIRECTORY_SEPARATOR));
        if ($fullPath === false) {
            return null;
        }

        if ($fullPath !== $rootPath && ! str_starts_with($fullPath, $rootPath.DIRECTORY_SEPARATOR)) {
            return null;
        }

        if (! File::isFile($fullPath)) {
            return null;
        }

        $size = File::size($fullPath);
        if ($size > self::MAX_FILE_SIZE) {
            return '// File too large to display ('.number_format($size / 1024, 1).' KB). Maximum supported size is '.number_format(self::MAX_FILE_SIZE / 1024).' KB.';
        }

        return File::get($fullPath);
    }

    /**
     * Check if the worktree exists and is accessible.
     */
    public function hasWorktree(WorkItem $workItem): bool
    {
        return $workItem->worktree_path && File::isDirectory($workItem->worktree_path);
    }

    /**
     * Resolve the base branch for diff comparison.
     */
    protected function resolveBaseBranch(string $worktreePath): string
    {
        $result = Process::path($worktreePath)
            ->run('git rev-parse --verify origin/main 2>/dev/null');

        if ($result->successful()) {
            return 'origin/main';
        }

        $result = Process::path($worktreePath)
            ->run('git rev-parse --verify origin/master 2>/dev/null');

        if ($result->successful()) {
            return 'origin/master';
        }

        return 'HEAD~1';
    }

    /**
     * Parse diff stats output into structured data.
     *
     * @return array{files_changed: int, insertions: int, deletions: int}
     */
    protected function parseDiffStats(string $statsOutput): array
    {
        $stats = ['files_changed' => 0, 'insertions' => 0, 'deletions' => 0];

        if (preg_match('/(\d+) files? changed/', $statsOutput, $matches)) {
            $stats['files_changed'] = (int) $matches[1];
        }
        if (preg_match('/(\d+) insertions?/', $statsOutput, $matches)) {
            $stats['insertions'] = (int) $matches[1];
        }
        if (preg_match('/(\d+) deletions?/', $statsOutput, $matches)) {
            $stats['deletions'] = (int) $matches[1];
        }

        return $stats;
    }
}
