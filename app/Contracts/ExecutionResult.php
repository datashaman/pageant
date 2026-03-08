<?php

namespace App\Contracts;

class ExecutionResult
{
    public function __construct(
        public readonly string $stdout,
        public readonly string $stderr,
        public readonly int $exitCode,
    ) {}

    public function isSuccessful(): bool
    {
        return $this->exitCode === 0;
    }
}
