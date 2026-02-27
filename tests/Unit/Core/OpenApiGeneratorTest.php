<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Smallwork\Core\OpenApiGenerator;
use Smallwork\Core\Router;

class OpenApiGeneratorTest extends TestCase
{
    public function test_empty_router_generates_valid_minimal_spec(): void
    {
        $router = new Router();
        $generator = new OpenApiGenerator($router, title: 'My API', version: '1.0.0');
        $spec = $generator->generate();

        $this->assertEquals('3.0.0', $spec['openapi']);
        $this->assertEquals('My API', $spec['info']['title']);
        $this->assertEquals('1.0.0', $spec['info']['version']);
        $this->assertIsArray($spec['paths']);
        $this->assertEmpty($spec['paths']);
    }

    public function test_routes_appear_as_paths_with_correct_methods(): void
    {
        $router = new Router();
        $router->get('/users', ['UsersController', 'index']);
        $router->post('/users', ['UsersController', 'store']);
        $router->delete('/users/{id}', ['UsersController', 'destroy']);

        $generator = new OpenApiGenerator($router, title: 'Test', version: '0.1.0');
        $spec = $generator->generate();

        $this->assertArrayHasKey('/users', $spec['paths']);
        $this->assertArrayHasKey('get', $spec['paths']['/users']);
        $this->assertArrayHasKey('post', $spec['paths']['/users']);
        $this->assertArrayHasKey('/users/{id}', $spec['paths']);
        $this->assertArrayHasKey('delete', $spec['paths']['/users/{id}']);
    }

    public function test_route_parameters_extracted_as_openapi_path_parameters(): void
    {
        $router = new Router();
        $router->get('/users/{userId}/posts/{postId}', ['PostsController', 'show']);

        $generator = new OpenApiGenerator($router, title: 'Test', version: '1.0.0');
        $spec = $generator->generate();

        $params = $spec['paths']['/users/{userId}/posts/{postId}']['get']['parameters'];
        $this->assertCount(2, $params);

        $names = array_column($params, 'name');
        $this->assertContains('userId', $names);
        $this->assertContains('postId', $names);

        $this->assertEquals('path', $params[0]['in']);
        $this->assertTrue($params[0]['required']);
        $this->assertEquals('string', $params[0]['schema']['type']);
    }

    public function test_info_with_description(): void
    {
        $router = new Router();
        $generator = new OpenApiGenerator(
            $router,
            title: 'My API',
            version: '2.0.0',
            description: 'A wonderful API',
        );
        $spec = $generator->generate();

        $this->assertEquals('A wonderful API', $spec['info']['description']);
    }

    public function test_to_json_returns_valid_json(): void
    {
        $router = new Router();
        $router->get('/health', fn () => 'ok');

        $generator = new OpenApiGenerator($router, title: 'Test', version: '1.0.0');
        $json = $generator->toJson();

        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertNotNull($decoded);
        $this->assertEquals('3.0.0', $decoded['openapi']);
        $this->assertArrayHasKey('/health', $decoded['paths']);
    }

    public function test_grouped_routes_use_full_path(): void
    {
        $router = new Router();
        $router->group('/api/v1', function (Router $r) {
            $r->get('/users', ['UsersController', 'index']);
            $r->post('/users', ['UsersController', 'store']);
        });

        $generator = new OpenApiGenerator($router, title: 'Test', version: '1.0.0');
        $spec = $generator->generate();

        $this->assertArrayHasKey('/api/v1/users', $spec['paths']);
        $this->assertArrayHasKey('get', $spec['paths']['/api/v1/users']);
        $this->assertArrayHasKey('post', $spec['paths']['/api/v1/users']);
    }

    public function test_routes_without_parameters_have_empty_parameters(): void
    {
        $router = new Router();
        $router->get('/status', fn () => 'ok');

        $generator = new OpenApiGenerator($router, title: 'Test', version: '1.0.0');
        $spec = $generator->generate();

        $this->assertEmpty($spec['paths']['/status']['get']['parameters']);
    }

    public function test_operation_includes_responses(): void
    {
        $router = new Router();
        $router->get('/users', ['UsersController', 'index']);

        $generator = new OpenApiGenerator($router, title: 'Test', version: '1.0.0');
        $spec = $generator->generate();

        $this->assertArrayHasKey('responses', $spec['paths']['/users']['get']);
        $this->assertArrayHasKey('200', $spec['paths']['/users']['get']['responses']);
    }
}
