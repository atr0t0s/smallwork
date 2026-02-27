<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Smallwork\Core\Router;

class RouterTest extends TestCase
{
    public function test_register_and_match_get_route(): void
    {
        $router = new Router();
        $router->get('/users', ['UsersController', 'index']);
        $match = $router->match('GET', '/users');
        $this->assertNotNull($match);
        $this->assertEquals(['UsersController', 'index'], $match['handler']);
    }

    public function test_register_post_route(): void
    {
        $router = new Router();
        $router->post('/users', ['UsersController', 'store']);
        $match = $router->match('POST', '/users');
        $this->assertNotNull($match);
        $this->assertEquals(['UsersController', 'store'], $match['handler']);
    }

    public function test_route_parameters(): void
    {
        $router = new Router();
        $router->get('/users/{id}', ['UsersController', 'show']);
        $match = $router->match('GET', '/users/42');
        $this->assertNotNull($match);
        $this->assertEquals('42', $match['params']['id']);
    }

    public function test_multiple_route_parameters(): void
    {
        $router = new Router();
        $router->get('/users/{userId}/posts/{postId}', ['PostsController', 'show']);
        $match = $router->match('GET', '/users/5/posts/99');
        $this->assertEquals('5', $match['params']['userId']);
        $this->assertEquals('99', $match['params']['postId']);
    }

    public function test_no_match_returns_null(): void
    {
        $router = new Router();
        $router->get('/users', ['UsersController', 'index']);
        $this->assertNull($router->match('GET', '/posts'));
        $this->assertNull($router->match('POST', '/users'));
    }

    public function test_route_group_with_prefix(): void
    {
        $router = new Router();
        $router->group('/api/v1', function (Router $r) {
            $r->get('/users', ['UsersController', 'index']);
            $r->post('/chat', ['ChatController', 'create']);
        });
        $match = $router->match('GET', '/api/v1/users');
        $this->assertNotNull($match);
        $this->assertEquals(['UsersController', 'index'], $match['handler']);
        $match2 = $router->match('POST', '/api/v1/chat');
        $this->assertNotNull($match2);
    }

    public function test_route_group_with_middleware(): void
    {
        $router = new Router();
        $router->group('/api', function (Router $r) {
            $r->get('/data', ['DataController', 'index']);
        }, middleware: ['AuthMiddleware']);
        $match = $router->match('GET', '/api/data');
        $this->assertContains('AuthMiddleware', $match['middleware']);
    }

    public function test_per_route_middleware(): void
    {
        $router = new Router();
        $router->get('/admin', ['AdminController', 'index'], middleware: ['AdminOnly']);
        $match = $router->match('GET', '/admin');
        $this->assertContains('AdminOnly', $match['middleware']);
    }

    public function test_put_delete_patch(): void
    {
        $router = new Router();
        $router->put('/users/{id}', ['UsersController', 'update']);
        $router->delete('/users/{id}', ['UsersController', 'destroy']);
        $router->patch('/users/{id}', ['UsersController', 'patch']);
        $this->assertNotNull($router->match('PUT', '/users/1'));
        $this->assertNotNull($router->match('DELETE', '/users/1'));
        $this->assertNotNull($router->match('PATCH', '/users/1'));
    }

    public function test_nested_groups(): void
    {
        $router = new Router();
        $router->group('/api', function (Router $r) {
            $r->group('/v1', function (Router $r) {
                $r->get('/users', ['UsersController', 'index']);
            });
        });
        $match = $router->match('GET', '/api/v1/users');
        $this->assertNotNull($match);
    }
}
