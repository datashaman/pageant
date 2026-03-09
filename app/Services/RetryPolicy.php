<?php

namespace App\Services;

use App\Enums\FailureCategory;

class RetryPolicy
{
    public function __construct(
        public readonly int $maxAttempts,
        public readonly int $backoffSeconds,
        public readonly float $backoffMultiplier,
    ) {}

    /**
     * @return array<string, self>
     */
    public static function defaults(): array
    {
        return [
            FailureCategory::RateLimit->value => new self(maxAttempts: 5, backoffSeconds: 10, backoffMultiplier: 2.0),
            FailureCategory::GithubApi->value => new self(maxAttempts: 3, backoffSeconds: 5, backoffMultiplier: 2.0),
            FailureCategory::ToolError->value => new self(maxAttempts: 2, backoffSeconds: 2, backoffMultiplier: 1.0),
            FailureCategory::ModelError->value => new self(maxAttempts: 2, backoffSeconds: 3, backoffMultiplier: 1.5),
            FailureCategory::Timeout->value => new self(maxAttempts: 2, backoffSeconds: 5, backoffMultiplier: 2.0),
            FailureCategory::Unknown->value => new self(maxAttempts: 1, backoffSeconds: 0, backoffMultiplier: 1.0),
        ];
    }

    public static function forCategory(FailureCategory $category): self
    {
        return static::defaults()[$category->value];
    }

    public function delayForAttempt(int $attempt): int
    {
        if ($attempt <= 1) {
            return $this->backoffSeconds;
        }

        return (int) round($this->backoffSeconds * ($this->backoffMultiplier ** ($attempt - 1)));
    }
}
