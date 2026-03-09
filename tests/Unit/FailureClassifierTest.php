<?php

use App\Enums\FailureCategory;
use App\Services\FailureClassifier;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->classifier = new FailureClassifier;
});

function makeRequestException(int $status): RequestException
{
    $response = new Response(new \GuzzleHttp\Psr7\Response($status));

    return new RequestException($response);
}

describe('FailureClassifier', function () {
    it('classifies ConnectionException as timeout', function () {
        $exception = new ConnectionException('Connection timed out');

        expect($this->classifier->classify($exception))->toBe(FailureCategory::Timeout);
    });

    it('classifies timeout messages as timeout', function (string $message) {
        $exception = new RuntimeException($message);

        expect($this->classifier->classify($exception))->toBe(FailureCategory::Timeout);
    })->with([
        'timed out' => 'Operation timed out after 30 seconds',
        'timeout' => 'Request timeout exceeded',
    ]);

    it('classifies 429 RequestException as rate limit', function () {
        $exception = makeRequestException(429);

        expect($this->classifier->classify($exception))->toBe(FailureCategory::RateLimit);
    });

    it('classifies 429 HttpException as rate limit', function () {
        $exception = new HttpException(429, 'Too Many Requests');

        expect($this->classifier->classify($exception))->toBe(FailureCategory::RateLimit);
    });

    it('classifies rate limit messages as rate limit', function (string $message) {
        $exception = new RuntimeException($message);

        expect($this->classifier->classify($exception))->toBe(FailureCategory::RateLimit);
    })->with([
        'rate limit' => 'API rate limit exceeded',
        'too many requests' => 'Too many requests, please slow down',
        '429 status' => 'HTTP 429 response received',
    ]);

    it('classifies 4xx/5xx RequestException as github api error', function (int $status) {
        $exception = makeRequestException($status);

        expect($this->classifier->classify($exception))->toBe(FailureCategory::GithubApi);
    })->with([403, 404, 500, 502, 503]);

    it('classifies github-related messages as github api error', function () {
        $exception = new RuntimeException('Error from api.github.com: not found');

        expect($this->classifier->classify($exception))->toBe(FailureCategory::GithubApi);
    });

    it('classifies model/provider errors as model error', function (string $message) {
        $exception = new RuntimeException($message);

        expect($this->classifier->classify($exception))->toBe(FailureCategory::ModelError);
    })->with([
        'anthropic' => 'Anthropic API returned an error',
        'openai' => 'OpenAI completion failed',
        'model' => 'Model inference error',
        'provider' => 'AI provider unavailable',
    ]);

    it('classifies tool execution errors as tool error', function (string $message) {
        $exception = new RuntimeException($message);

        expect($this->classifier->classify($exception))->toBe(FailureCategory::ToolError);
    })->with([
        'tool failed' => 'Tool execution failed: bash_tool',
        'function call' => 'Invalid function call parameters',
        'tool_use' => 'Error in tool_use block',
    ]);

    it('classifies unknown exceptions as unknown', function () {
        $exception = new RuntimeException('Something completely unexpected happened');

        expect($this->classifier->classify($exception))->toBe(FailureCategory::Unknown);
    });

    it('prioritizes timeout over other categories', function () {
        $exception = new ConnectionException('GitHub API connection timed out');

        expect($this->classifier->classify($exception))->toBe(FailureCategory::Timeout);
    });

    it('prioritizes rate limit over github api for 429', function () {
        $exception = makeRequestException(429);

        expect($this->classifier->classify($exception))->toBe(FailureCategory::RateLimit);
    });
});
