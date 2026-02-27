<?php
// tests/Unit/AI/AutoSummarizerTest.php
declare(strict_types=1);
namespace Tests\Unit\AI;

use PHPUnit\Framework\TestCase;
use Smallwork\AI\Gateway;
use Smallwork\AI\Middleware\AutoSummarizer;
use Smallwork\AI\Providers\ProviderInterface;
use Smallwork\Core\Request;
use Smallwork\Core\Response;

class AutoSummarizerTest extends TestCase
{
    private function makeGateway(string $summaryResponse = 'A brief summary'): Gateway
    {
        $provider = new class($summaryResponse) implements ProviderInterface {
            public array $lastMessages = [];
            public int $callCount = 0;
            public function __construct(private string $response) {}
            public function chat(array $messages, array $options = []): array {
                $this->lastMessages = $messages;
                $this->callCount++;
                return ['content' => $this->response, 'usage' => ['total_tokens' => 10], 'model' => 'mock'];
            }
            public function embed(string|array $input, array $options = []): array { return []; }
            public function streamChat(array $messages, callable $onChunk, array $options = []): array { return []; }
        };

        $gateway = new Gateway('mock');
        $gateway->register('mock', $provider);
        return $gateway;
    }

    private function getProvider(Gateway $gateway): object
    {
        return $gateway->getProvider('mock');
    }

    private function passThrough(): callable
    {
        return fn(Request $request) => Response::json(['ok' => true]);
    }

    public function test_long_text_gets_summarized_via_gateway(): void
    {
        $gateway = $this->makeGateway('This is the summary');
        $middleware = new AutoSummarizer($gateway);

        $longText = str_repeat('Hello world. ', 50); // well over 500 chars
        $request = Request::create('POST', '/api/chat', body: json_encode(['message' => $longText]));

        $capturedRequest = null;
        $next = function (Request $req) use (&$capturedRequest) {
            $capturedRequest = $req;
            return Response::json(['ok' => true]);
        };

        $middleware->handle($request, $next);

        $this->assertEquals('This is the summary', $capturedRequest->getAttribute('summary'));
        $this->assertEquals(1, $this->getProvider($gateway)->callCount);
    }

    public function test_short_text_uses_original_no_gateway_call(): void
    {
        $gateway = $this->makeGateway();
        $middleware = new AutoSummarizer($gateway);

        $shortText = 'Short message';
        $request = Request::create('POST', '/api/chat', body: json_encode(['message' => $shortText]));

        $capturedRequest = null;
        $next = function (Request $req) use (&$capturedRequest) {
            $capturedRequest = $req;
            return Response::json(['ok' => true]);
        };

        $middleware->handle($request, $next);

        $this->assertEquals('Short message', $capturedRequest->getAttribute('summary'));
        $this->assertEquals(0, $this->getProvider($gateway)->callCount);
    }

    public function test_no_content_passes_through_without_summary(): void
    {
        $gateway = $this->makeGateway();
        $middleware = new AutoSummarizer($gateway);

        $request = Request::create('GET', '/api/health');

        $capturedRequest = null;
        $next = function (Request $req) use (&$capturedRequest) {
            $capturedRequest = $req;
            return Response::json(['ok' => true]);
        };

        $middleware->handle($request, $next);

        $this->assertNull($capturedRequest->getAttribute('summary'));
        $this->assertEquals(0, $this->getProvider($gateway)->callCount);
    }

    public function test_custom_threshold(): void
    {
        $gateway = $this->makeGateway('Summarized');
        $middleware = new AutoSummarizer($gateway, threshold: 10);

        $text = 'This is longer than ten characters';
        $request = Request::create('POST', '/api/chat', body: json_encode(['content' => $text]));

        $capturedRequest = null;
        $next = function (Request $req) use (&$capturedRequest) {
            $capturedRequest = $req;
            return Response::json(['ok' => true]);
        };

        $middleware->handle($request, $next);

        $this->assertEquals('Summarized', $capturedRequest->getAttribute('summary'));
        $this->assertEquals(1, $this->getProvider($gateway)->callCount);
    }

    public function test_extracts_from_content_field(): void
    {
        $gateway = $this->makeGateway();
        $middleware = new AutoSummarizer($gateway);

        $request = Request::create('POST', '/api/chat', body: json_encode(['content' => 'Hello']));

        $capturedRequest = null;
        $next = function (Request $req) use (&$capturedRequest) {
            $capturedRequest = $req;
            return Response::json(['ok' => true]);
        };

        $middleware->handle($request, $next);

        $this->assertEquals('Hello', $capturedRequest->getAttribute('summary'));
    }

    public function test_extracts_from_text_field(): void
    {
        $gateway = $this->makeGateway();
        $middleware = new AutoSummarizer($gateway);

        $request = Request::create('POST', '/api/chat', body: json_encode(['text' => 'Hi there']));

        $capturedRequest = null;
        $next = function (Request $req) use (&$capturedRequest) {
            $capturedRequest = $req;
            return Response::json(['ok' => true]);
        };

        $middleware->handle($request, $next);

        $this->assertEquals('Hi there', $capturedRequest->getAttribute('summary'));
    }

    public function test_json_body_without_known_fields_passes_through(): void
    {
        $gateway = $this->makeGateway();
        $middleware = new AutoSummarizer($gateway);

        $request = Request::create('POST', '/api/chat', body: json_encode(['foo' => 'bar']));

        $capturedRequest = null;
        $next = function (Request $req) use (&$capturedRequest) {
            $capturedRequest = $req;
            return Response::json(['ok' => true]);
        };

        $middleware->handle($request, $next);

        $this->assertNull($capturedRequest->getAttribute('summary'));
    }
}
