<?php
// tests/Unit/Core/CorsMiddlewareTest.php
declare(strict_types=1);
namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Smallwork\Core\Middleware\CorsMiddleware;
use Smallwork\Core\Request;
use Smallwork\Core\Response;

class CorsMiddlewareTest extends TestCase
{
    public function test_adds_cors_headers(): void
    {
        $cors = new CorsMiddleware(['*']);
        $request = Request::create('GET', '/api/data');
        $response = $cors->handle($request, fn($r) => Response::json(['ok' => true]));
        $this->assertEquals('*', $response->header('Access-Control-Allow-Origin'));
        $this->assertNotNull($response->header('Access-Control-Allow-Methods'));
    }

    public function test_handles_preflight_options(): void
    {
        $cors = new CorsMiddleware(['https://example.com']);
        $request = Request::create('OPTIONS', '/api/data', headers: ['Origin' => 'https://example.com', 'Access-Control-Request-Method' => 'POST']);
        $response = $cors->handle($request, fn($r) => Response::json([]));
        $this->assertEquals(204, $response->status());
        $this->assertEquals('https://example.com', $response->header('Access-Control-Allow-Origin'));
    }

    public function test_rejects_disallowed_origin(): void
    {
        $cors = new CorsMiddleware(['https://allowed.com']);
        $request = Request::create('GET', '/api/data', headers: ['Origin' => 'https://evil.com']);
        $response = $cors->handle($request, fn($r) => Response::json(['ok' => true]));
        $this->assertNull($response->header('Access-Control-Allow-Origin'));
    }
}
