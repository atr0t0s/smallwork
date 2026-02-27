<?php
// tests/Unit/Core/RateLimitMiddlewareTest.php
declare(strict_types=1);
namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Smallwork\Core\Middleware\RateLimitMiddleware;
use Smallwork\Core\Request;
use Smallwork\Core\Response;

class RateLimitMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        RateLimitMiddleware::reset();
    }

    public function test_allows_requests_under_limit(): void
    {
        $limiter = new RateLimitMiddleware(maxRequests: 5, windowSeconds: 60);
        $request = Request::create('GET', '/api/data', headers: ['X-Forwarded-For' => '1.2.3.4']);
        $response = $limiter->handle($request, fn($r) => Response::json(['ok' => true]));
        $this->assertEquals(200, $response->status());
    }

    public function test_blocks_requests_over_limit(): void
    {
        $limiter = new RateLimitMiddleware(maxRequests: 2, windowSeconds: 60);
        for ($i = 0; $i < 2; $i++) {
            $request = Request::create('GET', '/api/data', headers: ['X-Forwarded-For' => '1.2.3.4']);
            $limiter->handle($request, fn($r) => Response::json(['ok' => true]));
        }
        $request = Request::create('GET', '/api/data', headers: ['X-Forwarded-For' => '1.2.3.4']);
        $response = $limiter->handle($request, fn($r) => Response::json(['ok' => true]));
        $this->assertEquals(429, $response->status());
    }

    public function test_different_ips_have_separate_limits(): void
    {
        $limiter = new RateLimitMiddleware(maxRequests: 1, windowSeconds: 60);
        $req1 = Request::create('GET', '/', headers: ['X-Forwarded-For' => '1.1.1.1']);
        $limiter->handle($req1, fn($r) => Response::json(['ok' => true]));
        $req2 = Request::create('GET', '/', headers: ['X-Forwarded-For' => '2.2.2.2']);
        $response = $limiter->handle($req2, fn($r) => Response::json(['ok' => true]));
        $this->assertEquals(200, $response->status());
    }

    public function test_adds_rate_limit_headers(): void
    {
        $limiter = new RateLimitMiddleware(maxRequests: 10, windowSeconds: 60);
        $request = Request::create('GET', '/', headers: ['X-Forwarded-For' => '1.1.1.1']);
        $response = $limiter->handle($request, fn($r) => Response::json(['ok' => true]));
        $this->assertNotNull($response->header('X-RateLimit-Limit'));
        $this->assertNotNull($response->header('X-RateLimit-Remaining'));
    }
}
