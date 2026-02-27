<?php
// tests/Unit/AI/ContentModerationTest.php
declare(strict_types=1);
namespace Tests\Unit\AI;

use PHPUnit\Framework\TestCase;
use Smallwork\AI\Gateway;
use Smallwork\AI\Middleware\ContentModeration;
use Smallwork\AI\Providers\ProviderInterface;
use Smallwork\Core\Request;
use Smallwork\Core\Response;

class ContentModerationTest extends TestCase
{
    private Gateway $gateway;
    private string $lastClassification;

    private function makeGateway(string $classification): Gateway
    {
        $this->lastClassification = $classification;
        $test = $this;

        $provider = new class($test) implements ProviderInterface {
            public function __construct(private ContentModerationTest $test) {}

            public function chat(array $messages, array $options = []): array
            {
                return [
                    'content' => $this->test->getClassification(),
                    'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 1, 'total_tokens' => 6],
                    'model' => 'mock',
                ];
            }

            public function embed(string|array $input, array $options = []): array
            {
                return [];
            }

            public function streamChat(array $messages, callable $onChunk, array $options = []): array
            {
                return [];
            }
        };

        $gateway = new Gateway('mock');
        $gateway->register('mock', $provider);
        return $gateway;
    }

    public function getClassification(): string
    {
        return $this->lastClassification;
    }

    private function nextHandler(): callable
    {
        return fn(Request $request) => Response::json(['ok' => true]);
    }

    public function test_safe_content_passes_through(): void
    {
        $gateway = $this->makeGateway('safe');
        $middleware = new ContentModeration($gateway);

        $request = Request::create('POST', '/api/chat', body: json_encode(['message' => 'Hello world']));
        $response = $middleware->handle($request, $this->nextHandler());

        $this->assertEquals(200, $response->status());
        $this->assertEquals('{"ok":true}', $response->body());
    }

    public function test_unsafe_content_blocked_with_422(): void
    {
        $gateway = $this->makeGateway('unsafe');
        $middleware = new ContentModeration($gateway);

        $request = Request::create('POST', '/api/chat', body: json_encode(['message' => 'harmful content here']));
        $response = $middleware->handle($request, $this->nextHandler());

        $this->assertEquals(422, $response->status());
        $body = json_decode($response->body(), true);
        $this->assertArrayHasKey('error', $body);
        $this->assertStringContainsString('content', strtolower($body['error']));
    }

    public function test_get_request_passes_through(): void
    {
        $gateway = $this->makeGateway('unsafe'); // would block if checked
        $middleware = new ContentModeration($gateway);

        $request = Request::create('GET', '/api/chat');
        $response = $middleware->handle($request, $this->nextHandler());

        $this->assertEquals(200, $response->status());
    }

    public function test_missing_content_fields_passes_through(): void
    {
        $gateway = $this->makeGateway('unsafe'); // would block if checked
        $middleware = new ContentModeration($gateway);

        $request = Request::create('POST', '/api/chat', body: json_encode(['username' => 'alice']));
        $response = $middleware->handle($request, $this->nextHandler());

        $this->assertEquals(200, $response->status());
    }

    public function test_checks_content_field(): void
    {
        $gateway = $this->makeGateway('unsafe');
        $middleware = new ContentModeration($gateway);

        $request = Request::create('POST', '/api/chat', body: json_encode(['content' => 'bad stuff']));
        $response = $middleware->handle($request, $this->nextHandler());

        $this->assertEquals(422, $response->status());
    }

    public function test_checks_text_field(): void
    {
        $gateway = $this->makeGateway('unsafe');
        $middleware = new ContentModeration($gateway);

        $request = Request::create('POST', '/api/chat', body: json_encode(['text' => 'bad stuff']));
        $response = $middleware->handle($request, $this->nextHandler());

        $this->assertEquals(422, $response->status());
    }

    public function test_custom_fields_configuration(): void
    {
        $gateway = $this->makeGateway('unsafe');
        $middleware = new ContentModeration($gateway, fields: ['body']);

        // Default fields should not be checked
        $request = Request::create('POST', '/api/chat', body: json_encode(['message' => 'bad stuff']));
        $response = $middleware->handle($request, $this->nextHandler());
        $this->assertEquals(200, $response->status());

        // Custom field should be checked
        $request = Request::create('POST', '/api/chat', body: json_encode(['body' => 'bad stuff']));
        $response = $middleware->handle($request, $this->nextHandler());
        $this->assertEquals(422, $response->status());
    }

    public function test_empty_body_passes_through(): void
    {
        $gateway = $this->makeGateway('unsafe');
        $middleware = new ContentModeration($gateway);

        $request = Request::create('POST', '/api/chat', body: '');
        $response = $middleware->handle($request, $this->nextHandler());

        $this->assertEquals(200, $response->status());
    }
}
