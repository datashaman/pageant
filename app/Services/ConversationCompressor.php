<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Laravel\Ai\Ai;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\MessageRole;
use Laravel\Ai\Messages\ToolResultMessage;

class ConversationCompressor
{
    /**
     * Average characters per token (approximate for English text).
     */
    protected const CHARS_PER_TOKEN = 4;

    /**
     * @param  array{
     *     context_window: int,
     *     threshold: float,
     *     summary_provider: string,
     *     summary_model: string|null,
     *     max_summary_tokens: int,
     * }  $config
     */
    public function __construct(
        protected int $contextWindow = 200000,
        protected float $threshold = 0.8,
        protected string $summaryProvider = 'anthropic',
        protected ?string $summaryModel = null,
        protected int $maxSummaryTokens = 500,
    ) {}

    /**
     * Create an instance from application configuration.
     */
    public static function fromConfig(): static
    {
        return new static(
            contextWindow: (int) config('conversation_compression.context_window', 200000),
            threshold: (float) config('conversation_compression.threshold', 0.8),
            summaryProvider: (string) config('conversation_compression.summary_provider', 'anthropic'),
            summaryModel: config('conversation_compression.summary_model'),
            maxSummaryTokens: (int) config('conversation_compression.max_summary_tokens', 500),
        );
    }

    /**
     * Estimate the total token count for a list of messages.
     *
     * @param  iterable<Message>  $messages
     */
    public function estimateTokenCount(iterable $messages): int
    {
        $totalChars = 0;

        foreach ($messages as $message) {
            $totalChars += $this->estimateMessageChars($message);
        }

        return (int) ceil($totalChars / static::CHARS_PER_TOKEN);
    }

    /**
     * Determine whether the conversation needs compression.
     *
     * @param  iterable<Message>  $messages
     */
    public function needsCompression(iterable $messages): bool
    {
        $tokenCount = $this->estimateTokenCount($messages);
        $limit = (int) ($this->contextWindow * $this->threshold);

        return $tokenCount >= $limit;
    }

    /**
     * Compress a conversation by summarizing older assistant/tool messages.
     *
     * Preserves system and user messages. Summarizes assistant responses
     * and tool outputs from older messages, keeping the most recent ones intact.
     *
     * @param  array<Message>  $messages
     * @return array<Message>
     */
    public function compress(array $messages, ?string $executionContext = null): array
    {
        if (! $this->needsCompression($messages)) {
            return $messages;
        }

        $tokenLimit = (int) ($this->contextWindow * $this->threshold);

        $preserved = [];
        $compressible = [];

        foreach ($messages as $message) {
            if ($this->isPreservedRole($message)) {
                $preserved[] = ['index' => count($preserved) + count($compressible), 'message' => $message];
            } else {
                $compressible[] = ['index' => count($preserved) + count($compressible), 'message' => $message];
            }
        }

        if (empty($compressible)) {
            return $messages;
        }

        $recentCount = max(1, (int) ceil(count($compressible) * 0.3));
        $olderMessages = array_slice($compressible, 0, count($compressible) - $recentCount);
        $recentMessages = array_slice($compressible, count($compressible) - $recentCount);

        if (empty($olderMessages)) {
            return $messages;
        }

        $summary = $this->generateSummary($olderMessages, $executionContext);

        $result = [];

        if ($summary !== '') {
            $result[] = new Message(MessageRole::User, "[Conversation Summary]\n{$summary}");
        }

        foreach ($preserved as $item) {
            $result[] = $item['message'];
        }

        foreach ($recentMessages as $item) {
            $result[] = $item['message'];
        }

        return array_values($result);
    }

    /**
     * Determine if a message role should be preserved during compression.
     */
    protected function isPreservedRole(Message $message): bool
    {
        return $message->role === MessageRole::User;
    }

    /**
     * Estimate the character count of a single message.
     */
    protected function estimateMessageChars(Message $message): int
    {
        $chars = strlen($message->content ?? '');

        if ($message instanceof AssistantMessage) {
            foreach ($message->toolCalls as $toolCall) {
                $chars += strlen($toolCall->name ?? '');
                $chars += strlen(json_encode($toolCall->arguments ?? []));
            }
        }

        if ($message instanceof ToolResultMessage) {
            foreach ($message->toolResults as $toolResult) {
                $chars += strlen($toolResult->name ?? '');
                $chars += strlen(is_string($toolResult->result) ? $toolResult->result : json_encode($toolResult->result));
            }
        }

        return $chars;
    }

    /**
     * Generate a summary of older messages using a cheaper model.
     *
     * @param  array<array{index: int, message: Message}>  $olderMessages
     */
    protected function generateSummary(array $olderMessages, ?string $executionContext = null): string
    {
        $textParts = [];

        foreach ($olderMessages as $item) {
            $message = $item['message'];
            $textParts[] = $this->formatMessageForSummary($message);
        }

        $conversationText = implode("\n---\n", $textParts);

        $summaryPrompt = $this->buildSummaryPrompt($conversationText, $executionContext);

        try {
            $agent = new AnonymousAgent($summaryPrompt, [], []);

            $response = $agent->prompt(
                'Summarize the following conversation context.',
                provider: $this->summaryProvider,
                model: $this->summaryModel,
            );

            return (string) $response;
        } catch (\Throwable $e) {
            Log::warning('Conversation compression summary generation failed', [
                'error' => $e->getMessage(),
            ]);

            return $this->buildFallbackSummary($olderMessages);
        }
    }

    /**
     * Build the prompt for the summary agent.
     */
    protected function buildSummaryPrompt(string $conversationText, ?string $executionContext = null): string
    {
        $parts = [
            'You are a conversation compression assistant. Your job is to create a concise summary of older conversation messages.',
            'Preserve key facts, decisions, and outcomes. Drop verbose tool outputs and redundant details.',
            "Keep the summary under {$this->maxSummaryTokens} tokens.",
            'Output only the summary text, no preamble.',
        ];

        if ($executionContext) {
            $parts[] = "Current execution context:\n{$executionContext}";
        }

        $parts[] = "Conversation to summarize:\n{$conversationText}";

        return implode("\n\n", $parts);
    }

    /**
     * Format a message for inclusion in the summary prompt.
     */
    protected function formatMessageForSummary(Message $message): string
    {
        $role = $message->role->value;

        if ($message instanceof ToolResultMessage) {
            $parts = [];
            foreach ($message->toolResults as $toolResult) {
                $result = is_string($toolResult->result)
                    ? $toolResult->result
                    : json_encode($toolResult->result);

                if (strlen($result) > 500) {
                    $result = substr($result, 0, 497).'...';
                }

                $parts[] = "[Tool: {$toolResult->name}] {$result}";
            }

            return "[{$role}] ".implode("\n", $parts);
        }

        if ($message instanceof AssistantMessage && $message->toolCalls->isNotEmpty()) {
            $toolNames = $message->toolCalls->pluck('name')->implode(', ');

            return "[{$role}] {$message->content} [Called tools: {$toolNames}]";
        }

        return "[{$role}] {$message->content}";
    }

    /**
     * Build a fallback summary when AI summarization fails.
     *
     * @param  array<array{index: int, message: Message}>  $olderMessages
     */
    protected function buildFallbackSummary(array $olderMessages): string
    {
        $lines = [];

        foreach ($olderMessages as $item) {
            $message = $item['message'];
            $content = $message->content ?? '';

            if ($message instanceof ToolResultMessage) {
                foreach ($message->toolResults as $toolResult) {
                    $lines[] = "- Tool '{$toolResult->name}' was called";
                }

                continue;
            }

            if ($message instanceof AssistantMessage && $message->toolCalls->isNotEmpty()) {
                $toolNames = $message->toolCalls->pluck('name')->implode(', ');
                $lines[] = "- Assistant called: {$toolNames}";

                continue;
            }

            if (strlen($content) > 100) {
                $content = substr($content, 0, 97).'...';
            }

            if ($content !== '') {
                $lines[] = "- [{$message->role->value}] {$content}";
            }
        }

        return implode("\n", $lines);
    }
}
