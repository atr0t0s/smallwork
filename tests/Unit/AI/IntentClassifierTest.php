<?php
// tests/Unit/AI/IntentClassifierTest.php
declare(strict_types=1);
namespace Tests\Unit\AI;

use PHPUnit\Framework\TestCase;
use Smallwork\AI\Gateway;
use Smallwork\AI\Middleware\IntentClassifier;
use Smallwork\AI\Providers\ProviderInterface;
use Smallwork\Core\Request;
use Smallwork\Core\Response;

class IntentClassifierTest extends TestCase
{
    private function mockGateway(string $chatResponse = 'question'): Gateway
    {
        $provider = new class($chatResponse) implements ProviderInterface {
            public function __construct(private string $resp) {}
            public function chat(array $messages, array $options = []): array {
                return ['content' => $this->resp, 'usage' => ['total_tokens' => 5], 'model' => 'mock'];
            }
            public function embed(string|array $input, array $options = []): array { return []; }
            public function streamChat(array $messages, callable $onChunk, array $options = []): array {
                return ['content' => '', 'usage' => ['total_tokens' => 0], 'model' => 'mock'];
            }
        };

        $gateway = new Gateway('mock');
        $gateway->register('mock', $provider);
        return $gateway;
    }

    public function test_classifies_intent_from_message_field(): void
    {
        $gateway = $this->mockGateway('question');
        $middleware = new IntentClassifier($gateway);

        $request = Request::create('POST', '/chat', body: json_encode(['message' => 'What is PHP?']));

        $capturedIntent = null;
        $next = function (Request $req) use (&$capturedIntent): Response {
            $capturedIntent = $req->getAttribute('intent');
            return Response::json(['ok' => true]);
        };

        $middleware->handle($request, $next);

        $this->assertEquals('question', $capturedIntent);
    }

    public function test_classifies_intent_from_content_field(): void
    {
        $gateway = $this->mockGateway('command');
        $middleware = new IntentClassifier($gateway);

        $request = Request::create('POST', '/chat', body: json_encode(['content' => 'Deploy the app']));

        $capturedIntent = null;
        $next = function (Request $req) use (&$capturedIntent): Response {
            $capturedIntent = $req->getAttribute('intent');
            return Response::json(['ok' => true]);
        };

        $middleware->handle($request, $next);

        $this->assertEquals('command', $capturedIntent);
    }

    public function test_classifies_intent_from_text_field(): void
    {
        $gateway = $this->mockGateway('feedback');
        $middleware = new IntentClassifier($gateway);

        $request = Request::create('POST', '/chat', body: json_encode(['text' => 'Great work!']));

        $capturedIntent = null;
        $next = function (Request $req) use (&$capturedIntent): Response {
            $capturedIntent = $req->getAttribute('intent');
            return Response::json(['ok' => true]);
        };

        $middleware->handle($request, $next);

        $this->assertEquals('feedback', $capturedIntent);
    }

    public function test_get_request_gets_unknown_intent(): void
    {
        $gateway = $this->mockGateway('question');
        $middleware = new IntentClassifier($gateway);

        $request = Request::create('GET', '/chat');

        $capturedIntent = null;
        $next = function (Request $req) use (&$capturedIntent): Response {
            $capturedIntent = $req->getAttribute('intent');
            return Response::json(['ok' => true]);
        };

        $middleware->handle($request, $next);

        $this->assertEquals('unknown', $capturedIntent);
    }

    public function test_empty_body_gets_unknown_intent(): void
    {
        $gateway = $this->mockGateway('question');
        $middleware = new IntentClassifier($gateway);

        $request = Request::create('POST', '/chat', body: '');

        $capturedIntent = null;
        $next = function (Request $req) use (&$capturedIntent): Response {
            $capturedIntent = $req->getAttribute('intent');
            return Response::json(['ok' => true]);
        };

        $middleware->handle($request, $next);

        $this->assertEquals('unknown', $capturedIntent);
    }

    public function test_custom_intent_categories(): void
    {
        $gateway = $this->mockGateway('greeting');
        $middleware = new IntentClassifier($gateway, categories: ['greeting', 'farewell', 'question']);

        $request = Request::create('POST', '/chat', body: json_encode(['message' => 'Hello there!']));

        $capturedIntent = null;
        $next = function (Request $req) use (&$capturedIntent): Response {
            $capturedIntent = $req->getAttribute('intent');
            return Response::json(['ok' => true]);
        };

        $middleware->handle($request, $next);

        $this->assertEquals('greeting', $capturedIntent);
    }

    public function test_returns_response_from_next(): void
    {
        $gateway = $this->mockGateway('question');
        $middleware = new IntentClassifier($gateway);

        $request = Request::create('POST', '/chat', body: json_encode(['message' => 'Hello']));

        $next = function (Request $req): Response {
            return Response::json(['intent' => $req->getAttribute('intent')]);
        };

        $response = $middleware->handle($request, $next);

        $this->assertEquals(200, $response->status());
        $decoded = json_decode($response->body(), true);
        $this->assertEquals('question', $decoded['intent']);
    }

    public function test_unrecognized_ai_response_defaults_to_other(): void
    {
        $gateway = $this->mockGateway('I think this is a question about something');
        $middleware = new IntentClassifier($gateway);

        $request = Request::create('POST', '/chat', body: json_encode(['message' => 'Hello']));

        $capturedIntent = null;
        $next = function (Request $req) use (&$capturedIntent): Response {
            $capturedIntent = $req->getAttribute('intent');
            return Response::json(['ok' => true]);
        };

        $middleware->handle($request, $next);

        $this->assertEquals('other', $capturedIntent);
    }

    public function test_no_recognized_text_field_gets_unknown(): void
    {
        $gateway = $this->mockGateway('question');
        $middleware = new IntentClassifier($gateway);

        $request = Request::create('POST', '/chat', body: json_encode(['data' => 'something']));

        $capturedIntent = null;
        $next = function (Request $req) use (&$capturedIntent): Response {
            $capturedIntent = $req->getAttribute('intent');
            return Response::json(['ok' => true]);
        };

        $middleware->handle($request, $next);

        $this->assertEquals('unknown', $capturedIntent);
    }
}
