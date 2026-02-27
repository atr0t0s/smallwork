<?php
// tests/Unit/Core/AuthMiddlewareTest.php
declare(strict_types=1);
namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Smallwork\Core\Middleware\AuthMiddleware;
use Smallwork\Core\Request;
use Smallwork\Core\Response;
use Smallwork\Auth\JwtAuth;

class AuthMiddlewareTest extends TestCase
{
    private JwtAuth $jwt;
    private string $secret = 'test-secret-key-minimum-32-chars!!';

    protected function setUp(): void
    {
        $this->jwt = new JwtAuth($this->secret);
    }

    private function next(): callable
    {
        return fn(Request $r) => Response::json(['ok' => true, 'user' => $r->getAttribute('user')]);
    }

    public function test_jwt_allows_valid_token(): void
    {
        $token = $this->jwt->encode(['sub' => '123', 'role' => 'admin'], expiresIn: 3600);
        $middleware = AuthMiddleware::jwt($this->jwt);

        $request = Request::create('GET', '/api/data', headers: ['Authorization' => "Bearer $token"]);
        $response = $middleware->handle($request, $this->next());

        $this->assertEquals(200, $response->status());
        $body = json_decode($response->body(), true);
        $this->assertEquals('123', $body['user']['sub']);
    }

    public function test_jwt_rejects_missing_header(): void
    {
        $middleware = AuthMiddleware::jwt($this->jwt);
        $request = Request::create('GET', '/api/data');
        $response = $middleware->handle($request, $this->next());

        $this->assertEquals(401, $response->status());
    }

    public function test_jwt_rejects_invalid_token(): void
    {
        $middleware = AuthMiddleware::jwt($this->jwt);
        $request = Request::create('GET', '/api/data', headers: ['Authorization' => 'Bearer invalid.token.here']);
        $response = $middleware->handle($request, $this->next());

        $this->assertEquals(401, $response->status());
    }

    public function test_jwt_rejects_expired_token(): void
    {
        $token = $this->jwt->encode(['sub' => '123'], expiresIn: -10);
        $middleware = AuthMiddleware::jwt($this->jwt);
        $request = Request::create('GET', '/api/data', headers: ['Authorization' => "Bearer $token"]);
        $response = $middleware->handle($request, $this->next());

        $this->assertEquals(401, $response->status());
    }

    public function test_apikey_allows_valid_key(): void
    {
        // Mock ApiKeyAuth
        $mockApiKey = new class {
            public function verify(string $key): ?array {
                if ($key === 'sw_valid_key') {
                    return ['id' => 1, 'name' => 'Test', 'permissions' => ['read']];
                }
                return null;
            }
        };

        $middleware = AuthMiddleware::apiKey($mockApiKey);
        $request = Request::create('GET', '/api/data', headers: ['Authorization' => 'Bearer sw_valid_key']);
        $response = $middleware->handle($request, $this->next());

        $this->assertEquals(200, $response->status());
    }

    public function test_apikey_also_reads_x_api_key_header(): void
    {
        $mockApiKey = new class {
            public function verify(string $key): ?array {
                if ($key === 'sw_valid_key') {
                    return ['id' => 1, 'name' => 'Test', 'permissions' => ['read']];
                }
                return null;
            }
        };

        $middleware = AuthMiddleware::apiKey($mockApiKey);
        $request = Request::create('GET', '/api/data', headers: ['X-Api-Key' => 'sw_valid_key']);
        $response = $middleware->handle($request, $this->next());

        $this->assertEquals(200, $response->status());
    }

    public function test_apikey_rejects_invalid_key(): void
    {
        $mockApiKey = new class {
            public function verify(string $key): ?array { return null; }
        };

        $middleware = AuthMiddleware::apiKey($mockApiKey);
        $request = Request::create('GET', '/api/data', headers: ['Authorization' => 'Bearer invalid']);
        $response = $middleware->handle($request, $this->next());

        $this->assertEquals(401, $response->status());
    }
}
