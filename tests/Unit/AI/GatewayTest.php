<?php
// tests/Unit/AI/GatewayTest.php
declare(strict_types=1);
namespace Tests\Unit\AI;

use PHPUnit\Framework\TestCase;
use Smallwork\AI\Gateway;
use Smallwork\AI\Providers\ProviderInterface;

class GatewayTest extends TestCase
{
    private function mockProvider(string $chatResponse = 'Hello!', array $embedResponse = [0.1, 0.2]): ProviderInterface
    {
        return new class($chatResponse, $embedResponse) implements ProviderInterface {
            public function __construct(private string $chatResp, private array $embedResp) {}
            public function chat(array $messages, array $options = []): array {
                return [
                    'content' => $this->chatResp,
                    'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
                    'model' => $options['model'] ?? 'mock-model',
                ];
            }
            public function embed(string|array $input, array $options = []): array {
                if (is_string($input)) $input = [$input];
                return array_map(fn($t) => $this->embedResp, $input);
            }
            public function streamChat(array $messages, callable $onChunk, array $options = []): array {
                $onChunk('Hello');
                $onChunk(' World');
                return ['content' => 'Hello World', 'usage' => ['total_tokens' => 10], 'model' => 'mock'];
            }
        };
    }

    public function test_register_and_use_provider(): void
    {
        $gateway = new Gateway();
        $gateway->register('openai', $this->mockProvider('Hi from OpenAI'));

        $result = $gateway->chat([['role' => 'user', 'content' => 'Hello']], provider: 'openai');
        $this->assertEquals('Hi from OpenAI', $result['content']);
    }

    public function test_uses_default_provider(): void
    {
        $gateway = new Gateway('mock');
        $gateway->register('mock', $this->mockProvider('Default response'));

        $result = $gateway->chat([['role' => 'user', 'content' => 'Hi']]);
        $this->assertEquals('Default response', $result['content']);
    }

    public function test_embed(): void
    {
        $gateway = new Gateway('mock');
        $gateway->register('mock', $this->mockProvider(embedResponse: [0.5, 0.6, 0.7]));

        $vectors = $gateway->embed('Hello world');
        $this->assertCount(1, $vectors);
        $this->assertEquals([0.5, 0.6, 0.7], $vectors[0]);
    }

    public function test_embed_batch(): void
    {
        $gateway = new Gateway('mock');
        $gateway->register('mock', $this->mockProvider(embedResponse: [0.1, 0.2]));

        $vectors = $gateway->embed(['text1', 'text2']);
        $this->assertCount(2, $vectors);
    }

    public function test_stream_chat(): void
    {
        $gateway = new Gateway('mock');
        $gateway->register('mock', $this->mockProvider());

        $chunks = [];
        $result = $gateway->streamChat(
            [['role' => 'user', 'content' => 'Hi']],
            function (string $chunk) use (&$chunks) { $chunks[] = $chunk; }
        );

        $this->assertEquals(['Hello', ' World'], $chunks);
        $this->assertEquals('Hello World', $result['content']);
    }

    public function test_throws_on_unknown_provider(): void
    {
        $gateway = new Gateway();
        $this->expectException(\RuntimeException::class);
        $gateway->chat([['role' => 'user', 'content' => 'Hi']], provider: 'nonexistent');
    }

    public function test_throws_on_no_default(): void
    {
        $gateway = new Gateway();
        $gateway->register('mock', $this->mockProvider());
        // No default set, no provider specified
        $this->expectException(\RuntimeException::class);
        $gateway->chat([['role' => 'user', 'content' => 'Hi']]);
    }

    public function test_chat_passes_options(): void
    {
        $gateway = new Gateway('mock');
        $gateway->register('mock', $this->mockProvider());

        $result = $gateway->chat(
            [['role' => 'user', 'content' => 'Hi']],
            options: ['model' => 'gpt-4o', 'temperature' => 0.7]
        );
        $this->assertEquals('gpt-4o', $result['model']);
    }

    public function test_returns_usage_info(): void
    {
        $gateway = new Gateway('mock');
        $gateway->register('mock', $this->mockProvider());

        $result = $gateway->chat([['role' => 'user', 'content' => 'Hi']]);
        $this->assertArrayHasKey('usage', $result);
        $this->assertEquals(15, $result['usage']['total_tokens']);
    }
}
