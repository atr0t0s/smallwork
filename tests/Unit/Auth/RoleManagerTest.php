<?php
// tests/Unit/Auth/RoleManagerTest.php
declare(strict_types=1);
namespace Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use Smallwork\Auth\RoleManager;
use Smallwork\Core\Middleware\RoleMiddleware;
use Smallwork\Core\Request;
use Smallwork\Core\Response;

class RoleManagerTest extends TestCase
{
    private RoleManager $roles;

    protected function setUp(): void
    {
        $this->roles = new RoleManager([
            'admin' => ['chat:read', 'chat:write', 'embed:read', 'embed:write', 'users:manage'],
            'user' => ['chat:read', 'chat:write', 'embed:read'],
            'service' => ['chat:read', 'embed:read', 'embed:write'],
        ]);
    }

    public function test_has_permission(): void
    {
        $this->assertTrue($this->roles->hasPermission('admin', 'users:manage'));
        $this->assertTrue($this->roles->hasPermission('user', 'chat:read'));
        $this->assertFalse($this->roles->hasPermission('user', 'users:manage'));
    }

    public function test_get_permissions_for_role(): void
    {
        $perms = $this->roles->getPermissions('admin');
        $this->assertContains('users:manage', $perms);
        $this->assertCount(5, $perms);
    }

    public function test_unknown_role_has_no_permissions(): void
    {
        $this->assertFalse($this->roles->hasPermission('unknown', 'chat:read'));
        $this->assertEmpty($this->roles->getPermissions('unknown'));
    }

    public function test_role_exists(): void
    {
        $this->assertTrue($this->roles->roleExists('admin'));
        $this->assertFalse($this->roles->roleExists('superadmin'));
    }

    public function test_role_middleware_allows_matching_role(): void
    {
        $middleware = new RoleMiddleware($this->roles, 'admin');

        $request = Request::create('GET', '/admin');
        $request->setAttribute('user', ['sub' => '1', 'role' => 'admin']);

        $response = $middleware->handle($request, fn($r) => Response::json(['ok' => true]));
        $this->assertEquals(200, $response->status());
    }

    public function test_role_middleware_rejects_wrong_role(): void
    {
        $middleware = new RoleMiddleware($this->roles, 'admin');

        $request = Request::create('GET', '/admin');
        $request->setAttribute('user', ['sub' => '1', 'role' => 'user']);

        $response = $middleware->handle($request, fn($r) => Response::json(['ok' => true]));
        $this->assertEquals(403, $response->status());
    }

    public function test_role_middleware_rejects_no_user(): void
    {
        $middleware = new RoleMiddleware($this->roles, 'admin');
        $request = Request::create('GET', '/admin');

        $response = $middleware->handle($request, fn($r) => Response::json(['ok' => true]));
        $this->assertEquals(403, $response->status());
    }

    public function test_permission_middleware(): void
    {
        $middleware = RoleMiddleware::requirePermission($this->roles, 'embed:write');

        $request = Request::create('POST', '/embed');
        $request->setAttribute('user', ['sub' => '1', 'role' => 'admin']);

        $response = $middleware->handle($request, fn($r) => Response::json(['ok' => true]));
        $this->assertEquals(200, $response->status());
    }

    public function test_permission_middleware_rejects_missing_permission(): void
    {
        $middleware = RoleMiddleware::requirePermission($this->roles, 'users:manage');

        $request = Request::create('POST', '/users');
        $request->setAttribute('user', ['sub' => '1', 'role' => 'user']);

        $response = $middleware->handle($request, fn($r) => Response::json(['ok' => true]));
        $this->assertEquals(403, $response->status());
    }
}
