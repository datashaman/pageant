<?php

namespace App\Services;

use App\Exceptions\CommandDeniedException;

class CommandPolicy
{
    /**
     * @param  array<string>  $allowedPatterns  Glob patterns for allowed commands (e.g. 'php artisan *', 'npm run *')
     * @param  array<string>  $deniedPatterns  Glob patterns for denied commands (e.g. 'rm -rf *', 'curl *')
     */
    public function __construct(
        protected array $allowedPatterns = [],
        protected array $deniedPatterns = [],
    ) {}

    public function isAllowed(string $command): bool
    {
        $baseCommand = $this->extractBaseCommand($command);

        if ($this->allowedPatterns !== []) {
            return $this->matchesAny($baseCommand, $this->allowedPatterns)
                || $this->matchesAny($command, $this->allowedPatterns);
        }

        if ($this->deniedPatterns !== []) {
            return ! $this->matchesAny($baseCommand, $this->deniedPatterns)
                && ! $this->matchesAny($command, $this->deniedPatterns);
        }

        return true;
    }

    public function validate(string $command): void
    {
        if (! $this->isAllowed($command)) {
            throw new CommandDeniedException("Command not permitted: {$command}");
        }
    }

    /**
     * Extract the base command (first word) from a full command string.
     */
    protected function extractBaseCommand(string $command): string
    {
        $trimmed = ltrim($command);
        $parts = preg_split('/\s+/', $trimmed, 2);

        return $parts[0] ?? '';
    }

    /**
     * Check if a string matches any of the given glob patterns.
     *
     * @param  array<string>  $patterns
     */
    protected function matchesAny(string $value, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (fnmatch($pattern, $value)) {
                return true;
            }
        }

        return false;
    }
}
