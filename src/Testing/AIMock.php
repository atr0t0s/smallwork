<?php
// src/Testing/AIMock.php
declare(strict_types=1);
namespace Smallwork\Testing;

use Smallwork\AI\Gateway;
use Smallwork\AI\Providers\ProviderInterface;

class AIMock
{
    /**
     * Create a Gateway with a mock provider that returns the given chat response.
     */
    public static function chat(string $content, array $usage = []): Gateway
    {
        $usage = array_merge([
            'prompt_tokens' => 10,
            'completion_tokens' => 5,
            'total_tokens' => 15,
        ], $usage);

        $provider = new class($content, $usage) implements ProviderInterface {
            public function __construct(
                private string $content,
                private array $usage,
            ) {}

            public function chat(array $messages, array $options = []): array
            {
                return [
                    'content' => $this->content,
                    'usage' => $this->usage,
                    'model' => $options['model'] ?? 'mock-model',
                ];
            }

            public function embed(string|array $input, array $options = []): array
            {
                return [];
            }

            public function streamChat(array $messages, callable $onChunk, array $options = []): array
            {
                // Split content into word-based chunks
                $words = explode(' ', $this->content);
                foreach ($words as $i => $word) {
                    $chunk = ($i > 0 ? ' ' : '') . $word;
                    $onChunk($chunk);
                }
                return [
                    'content' => $this->content,
                    'usage' => $this->usage,
                    'model' => $options['model'] ?? 'mock-model',
                ];
            }
        };

        $gateway = new Gateway('mock');
        $gateway->register('mock', $provider);
        return $gateway;
    }

    /**
     * Create a Gateway with a mock provider that returns given embedding vectors.
     */
    public static function embed(array $vectors): Gateway
    {
        $provider = new class($vectors) implements ProviderInterface {
            public function __construct(private array $vectors) {}

            public function chat(array $messages, array $options = []): array
            {
                return ['content' => '', 'usage' => [], 'model' => 'mock'];
            }

            public function embed(string|array $input, array $options = []): array
            {
                if (is_string($input)) {
                    $input = [$input];
                }
                // Return vectors up to the number of inputs
                $result = [];
                foreach ($input as $i => $text) {
                    $result[] = $this->vectors[$i] ?? $this->vectors[0] ?? [];
                }
                return $result;
            }

            public function streamChat(array $messages, callable $onChunk, array $options = []): array
            {
                return ['content' => '', 'usage' => [], 'model' => 'mock'];
            }
        };

        $gateway = new Gateway('mock');
        $gateway->register('mock', $provider);
        return $gateway;
    }
}
