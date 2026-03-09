<?php

use App\Services\ConversationCompressor;
use Illuminate\Support\Collection;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\MessageRole;
use Laravel\Ai\Messages\ToolResultMessage;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\Data\ToolResult;

describe('Token Estimation', function () {
    it('estimates token count for simple messages', function () {
        $compressor = new ConversationCompressor;

        $messages = [
            new Message(MessageRole::User, 'Hello, how are you?'),
            new Message(MessageRole::Assistant, 'I am fine, thank you!'),
        ];

        $tokens = $compressor->estimateTokenCount($messages);

        expect($tokens)->toBeGreaterThan(0)
            ->and($tokens)->toBeLessThan(100);
    });

    it('estimates higher token count for messages with tool results', function () {
        $compressor = new ConversationCompressor;

        $simpleMessages = [
            new Message(MessageRole::User, 'Hello'),
        ];

        $toolResults = new Collection([
            new ToolResult(
                id: 'tr-1',
                name: 'read_file',
                arguments: ['path' => '/some/file.php'],
                result: str_repeat('x', 1000),
            ),
        ]);

        $messagesWithTools = [
            new Message(MessageRole::User, 'Hello'),
            new ToolResultMessage($toolResults),
        ];

        $simpleTokens = $compressor->estimateTokenCount($simpleMessages);
        $toolTokens = $compressor->estimateTokenCount($messagesWithTools);

        expect($toolTokens)->toBeGreaterThan($simpleTokens);
    });

    it('counts assistant tool calls in token estimation', function () {
        $compressor = new ConversationCompressor;

        $toolCalls = new Collection([
            new ToolCall(
                id: 'tc-1',
                name: 'search_issues',
                arguments: ['query' => 'bug fix', 'repo' => 'acme/widgets'],
            ),
        ]);

        $messages = [
            new AssistantMessage('Let me search for issues.', $toolCalls),
        ];

        $tokens = $compressor->estimateTokenCount($messages);

        expect($tokens)->toBeGreaterThan(5);
    });
});

describe('Compression Threshold', function () {
    it('does not need compression when below threshold', function () {
        $compressor = new ConversationCompressor(
            contextWindow: 200000,
            threshold: 0.8,
        );

        $messages = [
            new Message(MessageRole::User, 'Short message'),
            new Message(MessageRole::Assistant, 'Short reply'),
        ];

        expect($compressor->needsCompression($messages))->toBeFalse();
    });

    it('needs compression when above threshold', function () {
        $compressor = new ConversationCompressor(
            contextWindow: 100,
            threshold: 0.5,
        );

        $messages = [
            new Message(MessageRole::User, str_repeat('a', 400)),
            new Message(MessageRole::Assistant, str_repeat('b', 400)),
        ];

        expect($compressor->needsCompression($messages))->toBeTrue();
    });

    it('respects custom threshold values', function () {
        $lowThreshold = new ConversationCompressor(
            contextWindow: 100,
            threshold: 0.1,
        );

        $highThreshold = new ConversationCompressor(
            contextWindow: 100,
            threshold: 0.99,
        );

        $messages = [
            new Message(MessageRole::User, str_repeat('a', 100)),
        ];

        expect($lowThreshold->needsCompression($messages))->toBeTrue()
            ->and($highThreshold->needsCompression($messages))->toBeFalse();
    });
});

describe('Compression Logic', function () {
    it('returns messages unchanged when below threshold', function () {
        $compressor = new ConversationCompressor(
            contextWindow: 200000,
            threshold: 0.8,
        );

        $messages = [
            new Message(MessageRole::User, 'Hello'),
            new Message(MessageRole::Assistant, 'Hi there'),
        ];

        $result = $compressor->compress($messages);

        expect($result)->toHaveCount(2);
    });

    it('preserves user messages during compression', function () {
        AnonymousAgent::fake(['Summary of older messages.']);

        $compressor = new ConversationCompressor(
            contextWindow: 100,
            threshold: 0.1,
        );

        $messages = [
            new Message(MessageRole::User, 'First user message'),
            new AssistantMessage(str_repeat('verbose response ', 50)),
            new Message(MessageRole::User, 'Second user message'),
            new AssistantMessage(str_repeat('another verbose response ', 50)),
            new Message(MessageRole::User, 'Third user message'),
            new AssistantMessage(str_repeat('yet another response ', 50)),
        ];

        $result = $compressor->compress($messages);

        $userMessages = array_filter($result, fn (Message $m) => $m->role === MessageRole::User);
        $userContents = array_map(fn (Message $m) => $m->content, $userMessages);

        expect($userContents)->toContain('First user message')
            ->and($userContents)->toContain('Second user message')
            ->and($userContents)->toContain('Third user message');
    });

    it('generates a summary message for compressed content', function () {
        AnonymousAgent::fake(['The agent previously analyzed the codebase and found issues.']);

        $compressor = new ConversationCompressor(
            contextWindow: 100,
            threshold: 0.1,
        );

        $messages = [
            new Message(MessageRole::User, 'Analyze the codebase'),
            new AssistantMessage(str_repeat('analysis result ', 100)),
            new Message(MessageRole::User, 'Fix the issues'),
            new AssistantMessage(str_repeat('fixing code ', 100)),
            new Message(MessageRole::User, 'Now create tests'),
            new AssistantMessage(str_repeat('writing tests ', 100)),
        ];

        $result = $compressor->compress($messages);

        $summaryMessages = array_filter(
            $result,
            fn (Message $m) => str_contains($m->content ?? '', '[Conversation Summary]'),
        );

        expect($summaryMessages)->not->toBeEmpty();
    });

    it('keeps recent assistant messages after compression', function () {
        AnonymousAgent::fake(['Summary of old messages.']);

        $compressor = new ConversationCompressor(
            contextWindow: 100,
            threshold: 0.1,
        );

        $messages = [
            new Message(MessageRole::User, 'First message'),
            new AssistantMessage('Old response 1 '.str_repeat('x', 200)),
            new Message(MessageRole::User, 'Second message'),
            new AssistantMessage('Old response 2 '.str_repeat('x', 200)),
            new Message(MessageRole::User, 'Third message'),
            new AssistantMessage('Recent response '.str_repeat('y', 200)),
        ];

        $result = $compressor->compress($messages);

        $assistantContents = array_map(
            fn (Message $m) => $m->content,
            array_filter($result, fn (Message $m) => $m->role === MessageRole::Assistant),
        );

        $hasRecent = false;
        foreach ($assistantContents as $content) {
            if (str_contains($content ?? '', 'Recent response')) {
                $hasRecent = true;
            }
        }

        expect($hasRecent)->toBeTrue();

        $userContents = array_map(
            fn (Message $m) => $m->content,
            array_filter($result, fn (Message $m) => $m->role === MessageRole::User && ! str_contains($m->content ?? '', '[Conversation Summary]')),
        );
        $userOrder = array_values($userContents);
        expect($userOrder[0] ?? '')->toBe('First message')
            ->and($userOrder[1] ?? '')->toBe('Second message')
            ->and($userOrder[2] ?? '')->toBe('Third message');
    });

    it('includes execution context in compression when provided', function () {
        AnonymousAgent::fake(['Summary with context.']);

        $compressor = new ConversationCompressor(
            contextWindow: 100,
            threshold: 0.1,
        );

        $messages = [
            new Message(MessageRole::User, 'Do something'),
            new AssistantMessage(str_repeat('verbose output ', 100)),
            new Message(MessageRole::User, 'Do more'),
            new AssistantMessage(str_repeat('more output ', 100)),
            new Message(MessageRole::User, 'Continue'),
            new AssistantMessage(str_repeat('continued output ', 100)),
        ];

        $result = $compressor->compress($messages, 'Step 2: Fix authentication bug');

        expect($result)->not->toBeEmpty();

        AnonymousAgent::assertPrompted(function ($prompt) {
            return str_contains($prompt->prompt, 'Summarize')
                || str_contains($prompt->agent->instructions(), 'authentication');
        });
    });
});

describe('Fallback Summary', function () {
    it('generates fallback summary when AI summarization fails', function () {
        AnonymousAgent::fake(function () {
            throw new RuntimeException('API unavailable');
        });

        $compressor = new ConversationCompressor(
            contextWindow: 100,
            threshold: 0.1,
        );

        $toolCalls = new Collection([
            new ToolCall(id: 'tc-1', name: 'read_file', arguments: ['path' => '/app.php']),
        ]);

        $messages = [
            new Message(MessageRole::User, 'Read the file'),
            new AssistantMessage('Reading the file now.', $toolCalls),
            new Message(MessageRole::User, 'Now edit it'),
            new AssistantMessage(str_repeat('editing ', 200)),
            new Message(MessageRole::User, 'Continue'),
            new AssistantMessage(str_repeat('continuing ', 200)),
        ];

        $result = $compressor->compress($messages);

        $summaryMessages = array_filter(
            $result,
            fn (Message $m) => str_contains($m->content ?? '', '[Conversation Summary]'),
        );

        expect($summaryMessages)->not->toBeEmpty();

        $summary = array_values($summaryMessages)[0]->content;
        expect($summary)->toContain('read_file');
    });
});

describe('Configuration', function () {
    it('creates instance from config', function () {
        config([
            'conversation_compression.context_window' => 150000,
            'conversation_compression.threshold' => 0.7,
            'conversation_compression.summary_provider' => 'openai',
            'conversation_compression.summary_model' => 'gpt-4o-mini',
            'conversation_compression.max_summary_tokens' => 300,
        ]);

        $compressor = ConversationCompressor::fromConfig();

        $reflection = new ReflectionClass($compressor);

        $contextWindow = $reflection->getProperty('contextWindow');
        $threshold = $reflection->getProperty('threshold');
        $summaryProvider = $reflection->getProperty('summaryProvider');
        $summaryModel = $reflection->getProperty('summaryModel');
        $maxSummaryTokens = $reflection->getProperty('maxSummaryTokens');

        expect($contextWindow->getValue($compressor))->toBe(150000)
            ->and($threshold->getValue($compressor))->toBe(0.7)
            ->and($summaryProvider->getValue($compressor))->toBe('openai')
            ->and($summaryModel->getValue($compressor))->toBe('gpt-4o-mini')
            ->and($maxSummaryTokens->getValue($compressor))->toBe(300);
    });
});

describe('Tool Result Compression', function () {
    it('truncates long tool results in summary formatting', function () {
        AnonymousAgent::fake(['Summarized tool results.']);

        $compressor = new ConversationCompressor(
            contextWindow: 100,
            threshold: 0.1,
        );

        $toolResults = new Collection([
            new ToolResult(
                id: 'tr-1',
                name: 'read_file',
                arguments: ['path' => '/large-file.php'],
                result: str_repeat('x', 2000),
            ),
        ]);

        $messages = [
            new Message(MessageRole::User, 'Read the file'),
            new ToolResultMessage($toolResults),
            new Message(MessageRole::User, 'Now fix it'),
            new AssistantMessage(str_repeat('fixing ', 200)),
            new Message(MessageRole::User, 'Continue'),
            new AssistantMessage(str_repeat('more fixes ', 200)),
        ];

        $result = $compressor->compress($messages);

        expect($result)->not->toBeEmpty();
    });
});
