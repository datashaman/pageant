<?php

use App\Enums\FailureCategory;
use App\Services\RetryPolicy;

describe('RetryPolicy', function () {
    it('has defaults for all failure categories', function () {
        $defaults = RetryPolicy::defaults();

        foreach (FailureCategory::cases() as $category) {
            expect($defaults)->toHaveKey($category->value);
        }
    });

    it('returns a policy for a given category', function () {
        $policy = RetryPolicy::forCategory(FailureCategory::RateLimit);

        expect($policy)->toBeInstanceOf(RetryPolicy::class)
            ->and($policy->maxAttempts)->toBe(5)
            ->and($policy->backoffSeconds)->toBe(10)
            ->and($policy->backoffMultiplier)->toBe(2.0);
    });

    it('allows only 1 attempt for unknown errors', function () {
        $policy = RetryPolicy::forCategory(FailureCategory::Unknown);

        expect($policy->maxAttempts)->toBe(1);
    });

    it('calculates exponential backoff delay', function () {
        $policy = new RetryPolicy(maxAttempts: 5, backoffSeconds: 10, backoffMultiplier: 2.0);

        expect($policy->delayForAttempt(1))->toBe(10)
            ->and($policy->delayForAttempt(2))->toBe(20)
            ->and($policy->delayForAttempt(3))->toBe(40);
    });

    it('returns base delay for first attempt', function () {
        $policy = new RetryPolicy(maxAttempts: 3, backoffSeconds: 5, backoffMultiplier: 3.0);

        expect($policy->delayForAttempt(1))->toBe(5);
    });

    it('applies multiplier of 1.0 as constant backoff', function () {
        $policy = new RetryPolicy(maxAttempts: 3, backoffSeconds: 2, backoffMultiplier: 1.0);

        expect($policy->delayForAttempt(1))->toBe(2)
            ->and($policy->delayForAttempt(2))->toBe(2)
            ->and($policy->delayForAttempt(3))->toBe(2);
    });

    it('has correct settings per category', function (FailureCategory $category, int $maxAttempts) {
        $policy = RetryPolicy::forCategory($category);

        expect($policy->maxAttempts)->toBe($maxAttempts);
    })->with([
        'rate_limit' => [FailureCategory::RateLimit, 5],
        'github_api' => [FailureCategory::GithubApi, 3],
        'tool_error' => [FailureCategory::ToolError, 2],
        'model_error' => [FailureCategory::ModelError, 2],
        'timeout' => [FailureCategory::Timeout, 2],
        'unknown' => [FailureCategory::Unknown, 1],
    ]);
});
