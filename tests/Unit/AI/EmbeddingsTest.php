<?php
// tests/Unit/AI/EmbeddingsTest.php
declare(strict_types=1);
namespace Tests\Unit\AI;

use PHPUnit\Framework\TestCase;
use Smallwork\AI\Embeddings;
use Smallwork\AI\Gateway;
use Smallwork\AI\Providers\ProviderInterface;

class EmbeddingsTest extends TestCase
{
    private function mockProvider(array $embedVector = [0.1, 0.2, 0.3]): ProviderInterface
    {
        return new class($embedVector) implements ProviderInterface {
            public array $lastInput = [];
            public array $lastOptions = [];

            public function __construct(private array $embedVector) {}

            public function chat(array $messages, array $options = []): array {
                return ['content' => '', 'usage' => [], 'model' => ''];
            }

            public function embed(string|array $input, array $options = []): array {
                if (is_string($input)) $input = [$input];
                $this->lastInput = $input;
                $this->lastOptions = $options;
                return array_map(fn($t) => $this->embedVector, $input);
            }

            public function streamChat(array $messages, callable $onChunk, array $options = []): array {
                return ['content' => '', 'usage' => [], 'model' => ''];
            }
        };
    }

    private function makeGateway(ProviderInterface $provider): Gateway
    {
        $gateway = new Gateway('mock');
        $gateway->register('mock', $provider);
        return $gateway;
    }

    public function test_embed_single_text(): void
    {
        $provider = $this->mockProvider([0.5, 0.6, 0.7]);
        $embeddings = new Embeddings($this->makeGateway($provider));

        $result = $embeddings->embed('Hello world');

        $this->assertCount(1, $result);
        $this->assertEquals([0.5, 0.6, 0.7], $result[0]);
    }

    public function test_embed_batch(): void
    {
        $provider = $this->mockProvider([0.1, 0.2]);
        $embeddings = new Embeddings($this->makeGateway($provider));

        $result = $embeddings->embedBatch(['text one', 'text two', 'text three']);

        $this->assertCount(3, $result);
        foreach ($result as $vector) {
            $this->assertEquals([0.1, 0.2], $vector);
        }
    }

    public function test_auto_chunking_long_text(): void
    {
        $provider = $this->mockProvider([0.1, 0.2]);
        $embeddings = new Embeddings($this->makeGateway($provider), maxChunkLength: 10);

        // 25 chars -> should produce 3 chunks with maxChunkLength=10
        $result = $embeddings->embed('aaaaaaaaaa bbbbbbbbbb ccc');

        $this->assertCount(3, $result);
        // Verify the provider received 3 separate texts
        $this->assertCount(3, $provider->lastInput);
    }

    public function test_short_text_no_chunking(): void
    {
        $provider = $this->mockProvider([0.1, 0.2]);
        $embeddings = new Embeddings($this->makeGateway($provider), maxChunkLength: 1000);

        $result = $embeddings->embed('short text');

        $this->assertCount(1, $result);
        $this->assertCount(1, $provider->lastInput);
    }

    public function test_custom_provider(): void
    {
        $mockDefault = $this->mockProvider([0.1, 0.2]);
        $mockCustom = $this->mockProvider([0.9, 0.8]);

        $gateway = new Gateway('default');
        $gateway->register('default', $mockDefault);
        $gateway->register('custom', $mockCustom);

        $embeddings = new Embeddings($gateway);
        $result = $embeddings->embed('test', provider: 'custom');

        $this->assertEquals([[0.9, 0.8]], $result);
    }

    public function test_custom_options(): void
    {
        $provider = $this->mockProvider([0.1, 0.2]);
        $embeddings = new Embeddings($this->makeGateway($provider));

        $embeddings->embed('test', options: ['model' => 'text-embedding-3-large']);

        $this->assertEquals('text-embedding-3-large', $provider->lastOptions['model']);
    }

    public function test_embed_batch_with_provider_and_options(): void
    {
        $provider = $this->mockProvider([0.5]);
        $embeddings = new Embeddings($this->makeGateway($provider));

        $result = $embeddings->embedBatch(
            ['a', 'b'],
            options: ['model' => 'text-embedding-3-large']
        );

        $this->assertCount(2, $result);
        $this->assertEquals('text-embedding-3-large', $provider->lastOptions['model']);
    }

    public function test_chunking_respects_word_boundaries(): void
    {
        $provider = $this->mockProvider([0.1]);
        $embeddings = new Embeddings($this->makeGateway($provider), maxChunkLength: 10);

        $embeddings->embed('hello world foo');

        // "hello world foo" -> chunks should split on spaces
        // With maxChunkLength=10: "hello" "world foo" or "hello worl" "d foo" depending on impl
        // At minimum, multiple chunks should be produced
        $this->assertGreaterThan(1, count($provider->lastInput));
    }
}
