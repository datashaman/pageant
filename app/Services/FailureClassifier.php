<?php

namespace App\Services;

use App\Enums\FailureCategory;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class FailureClassifier
{
    public function classify(\Throwable $exception): FailureCategory
    {
        if ($this->isTimeout($exception)) {
            return FailureCategory::Timeout;
        }

        if ($this->isRateLimit($exception)) {
            return FailureCategory::RateLimit;
        }

        if ($this->isGitHubApiError($exception)) {
            return FailureCategory::GithubApi;
        }

        if ($this->isModelError($exception)) {
            return FailureCategory::ModelError;
        }

        if ($this->isToolError($exception)) {
            return FailureCategory::ToolError;
        }

        return FailureCategory::Unknown;
    }

    protected function isTimeout(\Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        $message = strtolower($exception->getMessage());

        return str_contains($message, 'timed out')
            || str_contains($message, 'timeout')
            || str_contains($message, 'operation timed out');
    }

    protected function isRateLimit(\Throwable $exception): bool
    {
        if ($exception instanceof RequestException && $exception->response->status() === 429) {
            return true;
        }

        if ($exception instanceof HttpException && $exception->getStatusCode() === 429) {
            return true;
        }

        $message = strtolower($exception->getMessage());

        return str_contains($message, 'rate limit')
            || str_contains($message, 'too many requests')
            || str_contains($message, '429');
    }

    protected function isGitHubApiError(\Throwable $exception): bool
    {
        if ($exception instanceof RequestException) {
            $status = $exception->response->status();

            return $status >= 400 && $status !== 429;
        }

        $message = strtolower($exception->getMessage());

        return str_contains($message, 'github')
            || str_contains($message, 'api.github.com');
    }

    protected function isModelError(\Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'model')
            || str_contains($message, 'anthropic')
            || str_contains($message, 'openai')
            || str_contains($message, 'provider')
            || str_contains($message, 'ai service')
            || str_contains($message, 'completion')
            || str_contains($message, 'inference');
    }

    protected function isToolError(\Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'tool')
            || str_contains($message, 'function call')
            || str_contains($message, 'tool_use');
    }
}
