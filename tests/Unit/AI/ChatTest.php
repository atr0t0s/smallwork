<?php
// tests/Unit/AI/ChatTest.php
declare(strict_types=1);
namespace Tests\Unit\AI;

use PHPUnit\Framework\TestCase;
use Smallwork\AI\Chat;
use Smallwork\AI\Gateway;
use Smallwork\AI\Providers\ProviderInterface;

class ChatTest extends TestCase
{
    private Gateway $gateway;
    private int $chatCallCount;
    /** @var array<int, array> Captured messages for each chat call */
    private array $capturedMessages;
    /** @var array<int, array> Captured options for each chat call */
    private array $capturedOptions;

    protected function setUp(): void
    {
        $this->chatCallCount = 0;
        $this->capturedMessages = [];
        $this->capturedOptions = [];

        $test = $this;
        $provider = new class($test) implements ProviderInterface {
            public function __construct(private ChatTest $test) {}

            public function chat(array $messages, array $options = []): array
            {
                $this->test->recordChat($messages, $options);
                return [
                    'content' => 'Response #' . $this->test->getChatCallCount(),
                    'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
                    'model' => $options['model'] ?? 'mock-model',
                ];
            }

            public function embed(string|array $input, array $options = []): array
            {
                return [];
            }

            public function streamChat(array $messages, callable $onChunk, array $options = []): array
            {
                $this->test->recordChat($messages, $options);
                $onChunk('Streamed ');
                $onChunk('response');
                return [
                    'content' => 'Streamed response',
                    'usage' => ['prompt_tokens' => 8, 'completion_tokens' => 4, 'total_tokens' => 12],
                    'model' => $options['model'] ?? 'mock-model',
                ];
            }
        };

        $this->gateway = new Gateway('mock');
        $this->gateway->register('mock', $provider);
    }

    public function recordChat(array $messages, array $options): void
    {
        $this->chatCallCount++;
        $this->capturedMessages[] = $messages;
        $this->capturedOptions[] = $options;
    }

    public function getChatCallCount(): int
    {
        return $this->chatCallCount;
    }

    public function test_create_chat_with_system_prompt(): void
    {
        $chat = new Chat($this->gateway, systemPrompt: 'You are a helpful assistant.');

        $messages = $chat->getMessages();
        $this->assertCount(1, $messages);
        $this->assertEquals('system', $messages[0]['role']);
        $this->assertEquals('You are a helpful assistant.', $messages[0]['content']);
    }

    public function test_create_chat_without_system_prompt(): void
    {
        $chat = new Chat($this->gateway);

        $this->assertCount(0, $chat->getMessages());
    }

    public function test_send_message_and_get_response(): void
    {
        $chat = new Chat($this->gateway);

        $response = $chat->send('Hello');

        $this->assertEquals('Response #1', $response['content']);
        $this->assertArrayHasKey('usage', $response);
        $this->assertArrayHasKey('model', $response);
    }

    public function test_send_adds_user_and_assistant_messages_to_history(): void
    {
        $chat = new Chat($this->gateway);

        $chat->send('Hello');

        $messages = $chat->getMessages();
        $this->assertCount(2, $messages);
        $this->assertEquals('user', $messages[0]['role']);
        $this->assertEquals('Hello', $messages[0]['content']);
        $this->assertEquals('assistant', $messages[1]['role']);
        $this->assertEquals('Response #1', $messages[1]['content']);
    }

    public function test_system_prompt_included_in_messages_sent_to_gateway(): void
    {
        $chat = new Chat($this->gateway, systemPrompt: 'Be concise.');

        $chat->send('Hi');

        $sentMessages = $this->capturedMessages[0];
        $this->assertCount(2, $sentMessages);
        $this->assertEquals('system', $sentMessages[0]['role']);
        $this->assertEquals('Be concise.', $sentMessages[0]['content']);
        $this->assertEquals('user', $sentMessages[1]['role']);
        $this->assertEquals('Hi', $sentMessages[1]['content']);
    }

    public function test_message_history_accumulates_across_sends(): void
    {
        $chat = new Chat($this->gateway, systemPrompt: 'System.');

        $chat->send('First');
        $chat->send('Second');

        $messages = $chat->getMessages();
        // system + user1 + assistant1 + user2 + assistant2
        $this->assertCount(5, $messages);
        $this->assertEquals('system', $messages[0]['role']);
        $this->assertEquals('First', $messages[1]['content']);
        $this->assertEquals('Response #1', $messages[2]['content']);
        $this->assertEquals('Second', $messages[3]['content']);
        $this->assertEquals('Response #2', $messages[4]['content']);

        // Second call should have sent full history
        $sentMessages = $this->capturedMessages[1];
        $this->assertCount(4, $sentMessages); // system + user1 + assistant1 + user2
    }

    public function test_token_usage_accumulates(): void
    {
        $chat = new Chat($this->gateway);

        $chat->send('First');
        $chat->send('Second');

        $usage = $chat->getTotalUsage();
        $this->assertEquals(20, $usage['prompt_tokens']);
        $this->assertEquals(10, $usage['completion_tokens']);
        $this->assertEquals(30, $usage['total_tokens']);
    }

    public function test_stream_response(): void
    {
        $chat = new Chat($this->gateway);

        $chunks = [];
        $response = $chat->stream('Hello', function (string $chunk) use (&$chunks) {
            $chunks[] = $chunk;
        });

        $this->assertEquals(['Streamed ', 'response'], $chunks);
        $this->assertEquals('Streamed response', $response['content']);
    }

    public function test_stream_adds_to_history(): void
    {
        $chat = new Chat($this->gateway);

        $chat->stream('Hello', function (string $chunk) {});

        $messages = $chat->getMessages();
        $this->assertCount(2, $messages);
        $this->assertEquals('user', $messages[0]['role']);
        $this->assertEquals('Hello', $messages[0]['content']);
        $this->assertEquals('assistant', $messages[1]['role']);
        $this->assertEquals('Streamed response', $messages[1]['content']);
    }

    public function test_stream_accumulates_tokens(): void
    {
        $chat = new Chat($this->gateway);

        $chat->send('First');   // 15 total
        $chat->stream('Second', function (string $chunk) {}); // 12 total

        $usage = $chat->getTotalUsage();
        $this->assertEquals(18, $usage['prompt_tokens']);  // 10 + 8
        $this->assertEquals(9, $usage['completion_tokens']); // 5 + 4
        $this->assertEquals(27, $usage['total_tokens']);    // 15 + 12
    }

    public function test_custom_options_passed_to_gateway(): void
    {
        $chat = new Chat($this->gateway, options: [
            'temperature' => 0.3,
            'max_tokens' => 500,
            'model' => 'gpt-4o',
        ]);

        $chat->send('Hello');

        $sentOptions = $this->capturedOptions[0];
        $this->assertEquals(0.3, $sentOptions['temperature']);
        $this->assertEquals(500, $sentOptions['max_tokens']);
        $this->assertEquals('gpt-4o', $sentOptions['model']);
    }

    public function test_per_message_options_override_defaults(): void
    {
        $chat = new Chat($this->gateway, options: [
            'temperature' => 0.3,
            'max_tokens' => 500,
        ]);

        $chat->send('Hello', options: ['temperature' => 0.9]);

        $sentOptions = $this->capturedOptions[0];
        $this->assertEquals(0.9, $sentOptions['temperature']);
        $this->assertEquals(500, $sentOptions['max_tokens']);
    }

    public function test_custom_provider(): void
    {
        // Register a second provider
        $provider2 = new class implements ProviderInterface {
            public function chat(array $messages, array $options = []): array {
                return [
                    'content' => 'From provider2',
                    'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1, 'total_tokens' => 2],
                    'model' => 'p2-model',
                ];
            }
            public function embed(string|array $input, array $options = []): array { return []; }
            public function streamChat(array $messages, callable $onChunk, array $options = []): array { return []; }
        };
        $this->gateway->register('provider2', $provider2);

        $chat = new Chat($this->gateway, provider: 'provider2');
        $response = $chat->send('Hello');

        $this->assertEquals('From provider2', $response['content']);
    }

    public function test_add_message_manually(): void
    {
        $chat = new Chat($this->gateway);

        $chat->addMessage('user', 'Pre-loaded question');
        $chat->addMessage('assistant', 'Pre-loaded answer');

        $messages = $chat->getMessages();
        $this->assertCount(2, $messages);

        // Next send should include pre-loaded history
        $chat->send('Follow-up');
        $sentMessages = $this->capturedMessages[0];
        $this->assertCount(3, $sentMessages); // pre-user + pre-assistant + new user
    }

    public function test_get_total_usage_initially_zero(): void
    {
        $chat = new Chat($this->gateway);

        $usage = $chat->getTotalUsage();
        $this->assertEquals(0, $usage['prompt_tokens']);
        $this->assertEquals(0, $usage['completion_tokens']);
        $this->assertEquals(0, $usage['total_tokens']);
    }
}
