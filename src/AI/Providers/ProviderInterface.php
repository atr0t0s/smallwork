<?php
// src/AI/Providers/ProviderInterface.php
declare(strict_types=1);
namespace Smallwork\AI\Providers;

interface ProviderInterface
{
    /**
     * Send a chat completion request.
     * @param array $messages Array of ['role' => string, 'content' => string]
     * @param array $options ['model' => string, 'temperature' => float, 'max_tokens' => int, ...]
     * @return array ['content' => string, 'usage' => [...], 'model' => string]
     */
    public function chat(array $messages, array $options = []): array;

    /**
     * Generate embeddings for text(s).
     * @param string|array $input Single text or array of texts
     * @param array $options ['model' => string]
     * @return array Array of float arrays (one embedding vector per input)
     */
    public function embed(string|array $input, array $options = []): array;

    /**
     * Stream a chat completion, calling $onChunk for each text chunk.
     * @param callable $onChunk function(string $chunk): void
     * @return array Final result ['content' => string, 'usage' => [...], 'model' => string]
     */
    public function streamChat(array $messages, callable $onChunk, array $options = []): array;
}
