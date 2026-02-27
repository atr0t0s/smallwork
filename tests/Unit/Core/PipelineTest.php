<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Smallwork\Core\Middleware\Pipeline;
use Smallwork\Core\Request;
use Smallwork\Core\Response;

class PipelineTest extends TestCase
{
    public function test_runs_handler_with_no_middleware(): void
    {
        $pipeline = new Pipeline();
        $request = Request::create('GET', '/');
        $response = $pipeline->handle($request, [], function (Request $req) {
            return Response::json(['ok' => true]);
        });
        $this->assertEquals(200, $response->status());
        $this->assertStringContainsString('true', $response->body());
    }

    public function test_middleware_can_modify_response(): void
    {
        $middleware = new class {
            public function handle(Request $request, callable $next): Response {
                $response = $next($request);
                return $response->withHeader('X-Modified', 'yes');
            }
        };

        $pipeline = new Pipeline();
        $request = Request::create('GET', '/');
        $response = $pipeline->handle($request, [$middleware], function (Request $req) {
            return Response::json(['ok' => true]);
        });
        $this->assertEquals('yes', $response->header('X-Modified'));
    }

    public function test_middleware_can_short_circuit(): void
    {
        $authMiddleware = new class {
            public function handle(Request $request, callable $next): Response {
                if ($request->header('Authorization') === null) {
                    return Response::json(['error' => 'unauthorized'], 401);
                }
                return $next($request);
            }
        };

        $pipeline = new Pipeline();
        $request = Request::create('GET', '/');
        $response = $pipeline->handle($request, [$authMiddleware], function (Request $req) {
            return Response::json(['secret' => 'data']);
        });
        $this->assertEquals(401, $response->status());
    }

    public function test_middleware_executes_in_order(): void
    {
        $log = [];

        $first = new class($log) {
            private array $log;
            public function __construct(array &$log) { $this->log = &$log; }
            public function handle(Request $request, callable $next): Response {
                $this->log[] = 'first-before';
                $response = $next($request);
                $this->log[] = 'first-after';
                return $response;
            }
        };

        $second = new class($log) {
            private array $log;
            public function __construct(array &$log) { $this->log = &$log; }
            public function handle(Request $request, callable $next): Response {
                $this->log[] = 'second-before';
                $response = $next($request);
                $this->log[] = 'second-after';
                return $response;
            }
        };

        $pipeline = new Pipeline();
        $request = Request::create('GET', '/');
        $pipeline->handle($request, [$first, $second], function (Request $req) {
            return Response::json(['ok' => true]);
        });

        $this->assertEquals(['first-before', 'second-before', 'second-after', 'first-after'], $log);
    }
}
