<?php
// src/AI/Chat.php
declare(strict_types=1);
namespace Smallwork\AI;

class Chat
{
    /** @var array<int, array{role: string, content: string}> */
    private array $messages = [];

    /** @var array{prompt_tokens: int, completion_tokens: int, total_tokens: int} */
    private array $totalUsage = [
        'prompt_tokens' => 0,
        'completion_tokens' => 0,
        'total_tokens' => 0,
    ];

    public function __construct(
        private Gateway $gateway,
        ?string $systemPrompt = null,
        private ?string $provider = null,
        private array $options = [],
    ) {
        if ($systemPrompt !== null) {
            $this->messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }
    }

    /**
     * Send a user message and get a response.
     */
    public function send(string $message, array $options = []): array
    {
        $this->messages[] = ['role' => 'user', 'content' => $message];

        $mergedOptions = array_merge($this->options, $options);
        $response = $this->gateway->chat($this->messages, provider: $this->provider, options: $mergedOptions);

        $this->messages[] = ['role' => 'assistant', 'content' => $response['content']];
        $this->accumulateUsage($response['usage']);

        return $response;
    }

    /**
     * Stream a user message, calling $onChunk for each text chunk.
     */
    public function stream(string $message, callable $onChunk, array $options = []): array
    {
        $this->messages[] = ['role' => 'user', 'content' => $message];

        $mergedOptions = array_merge($this->options, $options);
        $response = $this->gateway->streamChat($this->messages, $onChunk, provider: $this->provider, options: $mergedOptions);

        $this->messages[] = ['role' => 'assistant', 'content' => $response['content']];
        $this->accumulateUsage($response['usage']);

        return $response;
    }

    /**
     * Manually add a message to the conversation history.
     */
    public function addMessage(string $role, string $content): void
    {
        $this->messages[] = ['role' => $role, 'content' => $content];
    }

    /**
     * Get the full message history.
     * @return array<int, array{role: string, content: string}>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Get accumulated token usage across all messages.
     * @return array{prompt_tokens: int, completion_tokens: int, total_tokens: int}
     */
    public function getTotalUsage(): array
    {
        return $this->totalUsage;
    }

    private function accumulateUsage(array $usage): void
    {
        $this->totalUsage['prompt_tokens'] += $usage['prompt_tokens'] ?? 0;
        $this->totalUsage['completion_tokens'] += $usage['completion_tokens'] ?? 0;
        $this->totalUsage['total_tokens'] += $usage['total_tokens'] ?? 0;
    }
}
