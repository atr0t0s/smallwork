<?php
// src/AI/Embeddings.php
declare(strict_types=1);
namespace Smallwork\AI;

class Embeddings
{
    public function __construct(
        private Gateway $gateway,
        private int $maxChunkLength = 8000,
    ) {}

    /**
     * Generate embeddings for a single text. Long text is auto-chunked.
     *
     * @return array<array<float>> One embedding vector per chunk
     */
    public function embed(string $text, ?string $provider = null, array $options = []): array
    {
        $chunks = $this->chunk($text);
        return $this->gateway->embed($chunks, $provider, $options);
    }

    /**
     * Generate embeddings for multiple texts at once.
     *
     * @param string[] $texts
     * @return array<array<float>> One embedding vector per text
     */
    public function embedBatch(array $texts, ?string $provider = null, array $options = []): array
    {
        return $this->gateway->embed($texts, $provider, $options);
    }

    /**
     * Split text into chunks respecting the max chunk length.
     * Splits on whitespace boundaries when possible.
     *
     * @return string[]
     */
    private function chunk(string $text): array
    {
        if (strlen($text) <= $this->maxChunkLength) {
            return [$text];
        }

        $chunks = [];
        $remaining = $text;

        while (strlen($remaining) > $this->maxChunkLength) {
            $segment = substr($remaining, 0, $this->maxChunkLength);

            // Try to split at last whitespace within the segment
            $lastSpace = strrpos($segment, ' ');
            if ($lastSpace !== false && $lastSpace > 0) {
                $chunks[] = substr($remaining, 0, $lastSpace);
                $remaining = ltrim(substr($remaining, $lastSpace));
            } else {
                // No whitespace found; hard split
                $chunks[] = $segment;
                $remaining = substr($remaining, $this->maxChunkLength);
            }
        }

        if ($remaining !== '') {
            $chunks[] = $remaining;
        }

        return $chunks;
    }
}
