<?php

namespace App\Services;

use App\Contracts\ExecutionDriver;
use App\Contracts\ExecutionResult;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Process\Process;

class LocalExecutionDriver implements ExecutionDriver
{
    private readonly string $resolvedBasePath;

    public function __construct(
        private readonly string $basePath,
    ) {
        if (! is_dir($basePath)) {
            mkdir($basePath, 0755, true);
        }

        $resolved = realpath($basePath);

        if ($resolved === false) {
            throw new RuntimeException("Unable to resolve base path: {$basePath}");
        }

        $this->resolvedBasePath = $resolved;
    }

    public function exec(string $command, ?int $timeout = null): ExecutionResult
    {
        $process = Process::fromShellCommandline($command, $this->resolvedBasePath);
        $process->setTimeout($timeout ?? 300);
        $process->run();

        return new ExecutionResult(
            stdout: $process->getOutput(),
            stderr: $process->getErrorOutput(),
            exitCode: $process->getExitCode(),
        );
    }

    public function readFile(string $path, ?int $offset = null, ?int $limit = null): string
    {
        $resolvedPath = $this->resolvePath($path);

        if (! is_file($resolvedPath)) {
            throw new RuntimeException("Path is not a file: {$path}");
        }

        $lines = file($resolvedPath, FILE_IGNORE_NEW_LINES);

        if ($lines === false) {
            throw new RuntimeException("Unable to read file: {$path}");
        }

        $startLine = $offset ?? 0;
        $selectedLines = $limit !== null
            ? array_slice($lines, $startLine, $limit)
            : array_slice($lines, $startLine);

        $output = [];
        foreach ($selectedLines as $index => $line) {
            $lineNumber = $startLine + $index + 1;
            $output[] = sprintf('%6d	%s', $lineNumber, $line);
        }

        return implode("\n", $output);
    }

    public function writeFile(string $path, string $content): void
    {
        $resolvedPath = $this->resolvePathForWrite($path);

        $directory = dirname($resolvedPath);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true)) {
            throw new RuntimeException("Unable to create directory: {$directory}");
        }

        if (file_put_contents($resolvedPath, $content) === false) {
            throw new RuntimeException("Unable to write file: {$path}");
        }
    }

    public function editFile(string $path, string $oldString, string $newString, bool $replaceAll = false): void
    {
        if ($oldString === '') {
            throw new InvalidArgumentException('old_string cannot be empty');
        }

        $resolvedPath = $this->resolvePath($path);

        $content = file_get_contents($resolvedPath);

        if ($content === false) {
            throw new RuntimeException("Unable to read file: {$path}");
        }

        $count = substr_count($content, $oldString);

        if ($count === 0) {
            throw new RuntimeException("String not found in file: {$path}");
        }

        if (! $replaceAll && $count > 1) {
            throw new RuntimeException("Multiple matches ({$count}) found in file: {$path}. Use replaceAll to replace all occurrences.");
        }

        if ($replaceAll) {
            $newContent = str_replace($oldString, $newString, $content);
        } else {
            $position = strpos($content, $oldString);
            $newContent = substr_replace($content, $newString, $position, strlen($oldString));
        }

        file_put_contents($resolvedPath, $newContent);
    }

    /**
     * @return array<int, string>
     */
    public function glob(string $pattern): array
    {
        $this->validatePath($pattern);

        $fullPattern = $this->resolvedBasePath.'/'.$pattern;
        $matches = glob($fullPattern, GLOB_BRACE);

        if ($matches === false) {
            return [];
        }

        $basePrefixLength = strlen($this->resolvedBasePath) + 1;
        $boundaryPrefix = $this->resolvedBasePath.'/';

        return array_values(array_filter(
            array_map(
                fn (string $match): ?string => str_starts_with($match, $boundaryPrefix)
                    ? substr($match, $basePrefixLength)
                    : null,
                $matches,
            ),
        ));
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, string>
     */
    public function grep(string $pattern, ?string $path = null, array $options = []): array
    {
        $searchPath = $path !== null ? $this->resolvePath($path) : $this->resolvedBasePath;
        $outputMode = $options['output_mode'] ?? 'content';
        $context = $options['context'] ?? null;
        $type = $options['type'] ?? null;

        $command = ['grep', '-rn'];

        if ($context !== null) {
            $command[] = '-C';
            $command[] = (string) $context;
        }

        if ($outputMode === 'files_with_matches') {
            $command[] = '-l';
        } elseif ($outputMode === 'count') {
            $command[] = '-c';
        }

        if ($type !== null) {
            $extensionMap = [
                'php' => '*.php',
                'js' => '*.js',
                'ts' => '*.ts',
                'css' => '*.css',
                'html' => '*.html',
                'json' => '*.json',
                'yaml' => '*.yaml',
                'yml' => '*.yml',
                'md' => '*.md',
                'py' => '*.py',
            ];

            if (isset($extensionMap[$type])) {
                $command[] = '--include='.$extensionMap[$type];
            }
        }

        $command[] = '--';
        $command[] = $pattern;
        $command[] = $searchPath;

        $process = new Process($command, $this->resolvedBasePath);
        $process->setTimeout(30);
        $process->run();

        $exitCode = $process->getExitCode();

        if ($exitCode === 2) {
            throw new RuntimeException('grep error: '.$process->getErrorOutput());
        }

        $output = trim($process->getOutput());

        if ($output === '') {
            return [];
        }

        $lines = explode("\n", $output);

        $basePrefixLength = strlen($this->resolvedBasePath) + 1;

        return array_map(
            function (string $line) use ($basePrefixLength): string {
                if (str_starts_with($line, $this->resolvedBasePath)) {
                    return substr($line, $basePrefixLength);
                }

                return $line;
            },
            $lines,
        );
    }

    /**
     * @return array<int, array{name: string, type: string, size: int}>
     */
    public function listDirectory(string $path = '.'): array
    {
        $resolvedPath = $this->resolvePath($path);

        if (! is_dir($resolvedPath)) {
            throw new RuntimeException("Directory not found: {$path}");
        }

        $entries = scandir($resolvedPath);

        if ($entries === false) {
            throw new RuntimeException("Unable to read directory: {$path}");
        }

        $results = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $fullPath = $resolvedPath.'/'.$entry;

            $results[] = [
                'name' => $entry,
                'type' => is_dir($fullPath) ? 'directory' : 'file',
                'size' => is_file($fullPath) ? (int) filesize($fullPath) : 0,
            ];
        }

        return $results;
    }

    public function getBasePath(): string
    {
        return $this->resolvedBasePath;
    }

    public function cleanup(): void
    {
        if (! is_dir($this->resolvedBasePath)) {
            return;
        }

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->resolvedBasePath, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST,
            );

            foreach ($iterator as $item) {
                if ($item->isDir()) {
                    @rmdir($item->getPathname());
                } else {
                    @unlink($item->getPathname());
                }
            }

            @rmdir($this->resolvedBasePath);
        } catch (\Throwable) {
            // Silently handle cleanup errors to avoid masking the original exception
        }
    }

    /**
     * Resolve a relative path against the base path, rejecting traversal attempts.
     */
    private function resolvePath(string $path): string
    {
        if ($path === '.') {
            return $this->resolvedBasePath;
        }

        $this->validatePath($path);

        $resolvedPath = realpath($this->resolvedBasePath.'/'.$path);

        if ($resolvedPath === false) {
            throw new RuntimeException("File not found: {$path}");
        }

        if ($resolvedPath !== $this->resolvedBasePath && ! str_starts_with($resolvedPath, $this->resolvedBasePath.'/')) {
            throw new InvalidArgumentException("Path traversal detected: {$path}");
        }

        return $resolvedPath;
    }

    /**
     * Resolve a path for write operations where the file may not exist yet.
     */
    private function resolvePathForWrite(string $path): string
    {
        $this->validatePath($path);

        $fullPath = $this->resolvedBasePath.'/'.$path;

        $parentDir = dirname($fullPath);
        $realParent = realpath($parentDir);

        if ($realParent !== false && $realParent !== $this->resolvedBasePath && ! str_starts_with($realParent, $this->resolvedBasePath.'/')) {
            throw new InvalidArgumentException("Path traversal detected: {$path}");
        }

        return $fullPath;
    }

    /**
     * Validate that a path is not absolute and does not contain traversal segments.
     */
    private function validatePath(string $path): void
    {
        if (str_starts_with($path, '/')) {
            throw new InvalidArgumentException("Absolute paths are not allowed: {$path}");
        }

        $segments = explode('/', $path);

        foreach ($segments as $segment) {
            if ($segment === '..') {
                throw new InvalidArgumentException("Path traversal is not allowed: {$path}");
            }
        }
    }
}
