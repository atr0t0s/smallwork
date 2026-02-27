# Smallwork Enterprise AI Upgrade - Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Transform Smallwork from a minimal PHP routing POC into a full-stack AI platform with enterprise features.

**Architecture:** Layered framework (`src/` for framework, `app/` for user code) with Composer autoloading, PSR-7-inspired Request/Response, middleware pipeline, DI container, multi-DB support, multi-provider AI gateway, Blade-like views, and CLI tooling. Built in 6 phases, each producing a working framework.

**Tech Stack:** PHP 8.2+, Composer, PHPUnit, PDO (SQLite/MySQL/PostgreSQL), Redis, Qdrant, OpenAI-compatible AI APIs, SSE streaming.

**Reference:** See `docs/plans/2026-02-27-enterprise-ai-upgrade-design.md` for full design rationale.

---

## Phase 1: Core Framework

### Task 1: Initialize Composer and Project Scaffolding

**Files:**
- Create: `composer.json`
- Create: `public/index.php`
- Create: `public/.htaccess`
- Create: `.env.example`
- Create: `smallwork` (CLI entry point)
- Create: `src/Core/App.php` (stub)
- Move: `index.php` → archived (replaced by `public/index.php`)
- Remove debug echo: `includes/autoload.php:5`

**Step 1: Install Composer**

```bash
# If not installed:
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
php -r "unlink('composer-setup.php');"
sudo mv composer.phar /usr/local/bin/composer
```

**Step 2: Create composer.json**

```json
{
    "name": "smallwork/smallwork",
    "description": "Small footprint full-stack AI framework for PHP",
    "type": "framework",
    "license": "MIT",
    "minimum-stability": "stable",
    "require": {
        "php": ">=8.2"
    },
    "require-dev": {
        "phpunit/phpunit": "^11.0"
    },
    "autoload": {
        "psr-4": {
            "Smallwork\\": "src/",
            "App\\": "app/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Tests\\": "tests/"
        }
    }
}
```

**Step 3: Create directory structure**

```bash
mkdir -p public src/Core src/Core/Middleware src/Database/Adapters src/Auth src/View src/AI/Providers src/AI/Middleware src/AI/Prompts src/Console app/Controllers/Api app/Controllers/Web app/Models app/Middleware app/Views/layouts app/Prompts tests/Unit/Core tests/Unit/Database tests/Unit/Auth tests/Unit/View tests/Unit/AI tests/Integration database/migrations storage/logs storage/cache
```

**Step 4: Create `.env.example`**

```env
APP_NAME=Smallwork
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_TIMEZONE=America/New_York

DB_DRIVER=sqlite
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=storage/db.sqlite
DB_USERNAME=
DB_PASSWORD=

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

AI_PROVIDER=openai
OPENAI_API_KEY=
ANTHROPIC_API_KEY=
GROK_API_KEY=
```

**Step 5: Create `public/.htaccess`**

```apache
Options +SymLinksIfOwnerMatch
RewriteEngine On
RewriteCond "%{REQUEST_FILENAME}" !-f
RewriteCond "%{REQUEST_FILENAME}" !-d
RewriteRule "^" "index.php" [PT]
```

**Step 6: Create `public/index.php` stub**

```php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Smallwork\Core\App;

$app = App::create(__DIR__ . '/..');
$app->run();
```

**Step 7: Create `smallwork` CLI entry point**

```php
#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Smallwork\Core\App;

$app = App::create(__DIR__);
$app->runCli($argv);
```

**Step 8: Create PHPUnit config**

Create `phpunit.xml`:
```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         stopOnFailure="false">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>tests/Integration</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>src</directory>
        </include>
    </source>
</phpunit>
```

**Step 9: Run Composer install**

```bash
composer install
```

**Step 10: Verify PHPUnit runs (no tests yet)**

```bash
vendor/bin/phpunit
```
Expected: "No tests executed" with exit code 0.

**Step 11: Create `.gitignore` update**

Add to `.gitignore`:
```
vendor/
.env
storage/logs/*.log
storage/cache/*
storage/db.sqlite
composer.lock
```

**Step 12: Commit**

```bash
git add -A
git commit -m "feat: initialize Composer, PHPUnit, and project scaffolding"
```

---

### Task 2: Request Class

**Files:**
- Create: `src/Core/Request.php`
- Create: `tests/Unit/Core/RequestTest.php`

**Step 1: Write the failing tests**

```php
<?php
// tests/Unit/Core/RequestTest.php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Smallwork\Core\Request;

class RequestTest extends TestCase
{
    public function test_creates_from_globals(): void
    {
        $request = Request::create('GET', '/api/v1/users', query: ['page' => '1']);

        $this->assertEquals('GET', $request->method());
        $this->assertEquals('/api/v1/users', $request->path());
        $this->assertEquals('1', $request->query('page'));
    }

    public function test_parses_json_body(): void
    {
        $request = Request::create('POST', '/api/chat', body: '{"message":"hello"}', headers: ['Content-Type' => 'application/json']);

        $this->assertEquals('hello', $request->json('message'));
        $this->assertEquals(['message' => 'hello'], $request->json());
    }

    public function test_input_merges_query_and_post(): void
    {
        $request = Request::create('POST', '/search', query: ['page' => '1'], post: ['q' => 'test']);

        $this->assertEquals('1', $request->input('page'));
        $this->assertEquals('test', $request->input('q'));
        $this->assertEquals('default', $request->input('missing', 'default'));
    }

    public function test_headers(): void
    {
        $request = Request::create('GET', '/', headers: ['Authorization' => 'Bearer token123', 'Accept' => 'application/json']);

        $this->assertEquals('Bearer token123', $request->header('Authorization'));
        $this->assertEquals('application/json', $request->header('Accept'));
        $this->assertNull($request->header('X-Missing'));
    }

    public function test_route_parameters(): void
    {
        $request = Request::create('GET', '/users/42');
        $request->setRouteParams(['id' => '42']);

        $this->assertEquals('42', $request->param('id'));
        $this->assertNull($request->param('missing'));
    }

    public function test_method_detection(): void
    {
        $request = Request::create('POST', '/');
        $this->assertTrue($request->isPost());
        $this->assertFalse($request->isGet());

        $get = Request::create('GET', '/');
        $this->assertTrue($get->isGet());
    }

    public function test_captures_from_server_globals(): void
    {
        // Simulate $_SERVER, $_GET, $_POST
        $request = Request::capture([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/api/test?foo=bar',
            'HTTP_AUTHORIZATION' => 'Bearer abc',
            'CONTENT_TYPE' => 'application/json',
        ], ['foo' => 'bar'], ['name' => 'test'], '{"data":1}');

        $this->assertEquals('POST', $request->method());
        $this->assertEquals('/api/test', $request->path());
        $this->assertEquals('bar', $request->query('foo'));
        $this->assertEquals('test', $request->input('name'));
        $this->assertEquals(1, $request->json('data'));
        $this->assertEquals('Bearer abc', $request->header('Authorization'));
    }
}
```

**Step 2: Run tests to verify they fail**

```bash
vendor/bin/phpunit tests/Unit/Core/RequestTest.php -v
```
Expected: FAIL — class `Smallwork\Core\Request` not found.

**Step 3: Write the implementation**

```php
<?php
// src/Core/Request.php

declare(strict_types=1);

namespace Smallwork\Core;

class Request
{
    private array $routeParams = [];

    private function __construct(
        private string $method,
        private string $path,
        private array $query,
        private array $post,
        private array $headers,
        private string $rawBody,
        private ?array $jsonCache = null,
    ) {}

    public static function create(
        string $method,
        string $path,
        array $query = [],
        array $post = [],
        string $body = '',
        array $headers = [],
    ): self {
        return new self($method, $path, $query, $post, $headers, $body);
    }

    public static function capture(
        array $server = [],
        array $get = [],
        array $post = [],
        string $rawBody = '',
    ): self {
        $server = $server ?: $_SERVER;
        $get = $get ?: $_GET;
        $post = $post ?: $_POST;
        $rawBody = $rawBody ?: (file_get_contents('php://input') ?: '');

        $method = strtoupper($server['REQUEST_METHOD'] ?? 'GET');
        $uri = $server['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        $headers = [];
        foreach ($server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', substr($key, 5));
                $name = ucwords(strtolower($name), '-');
                $headers[$name] = $value;
            }
        }
        if (isset($server['CONTENT_TYPE'])) {
            $headers['Content-Type'] = $server['CONTENT_TYPE'];
        }

        return new self($method, $path, $get, $post, $headers, $rawBody);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }
        return $this->query[$key] ?? $default;
    }

    public function input(?string $key = null, mixed $default = null): mixed
    {
        $merged = array_merge($this->query, $this->post);
        if ($key === null) {
            return $merged;
        }
        return $merged[$key] ?? $default;
    }

    public function json(?string $key = null, mixed $default = null): mixed
    {
        if ($this->jsonCache === null && $this->rawBody !== '') {
            $this->jsonCache = json_decode($this->rawBody, true) ?: [];
        }
        $data = $this->jsonCache ?? [];
        if ($key === null) {
            return $data;
        }
        return $data[$key] ?? $default;
    }

    public function header(string $name): ?string
    {
        // Case-insensitive header lookup
        foreach ($this->headers as $k => $v) {
            if (strcasecmp($k, $name) === 0) {
                return $v;
            }
        }
        return null;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function rawBody(): string
    {
        return $this->rawBody;
    }

    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    public function isGet(): bool
    {
        return $this->method === 'GET';
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function isPut(): bool
    {
        return $this->method === 'PUT';
    }

    public function isDelete(): bool
    {
        return $this->method === 'DELETE';
    }

    /**
     * Set a custom attribute on the request (used by middleware).
     */
    public function setAttribute(string $key, mixed $value): void
    {
        $this->post['_attrs'][$key] = $value;
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->post['_attrs'][$key] ?? $default;
    }
}
```

**Step 4: Run tests to verify they pass**

```bash
vendor/bin/phpunit tests/Unit/Core/RequestTest.php -v
```
Expected: All 7 tests pass.

**Step 5: Commit**

```bash
git add src/Core/Request.php tests/Unit/Core/RequestTest.php
git commit -m "feat: add Request class with query, JSON, header parsing"
```

---

### Task 3: Response Class

**Files:**
- Create: `src/Core/Response.php`
- Create: `tests/Unit/Core/ResponseTest.php`

**Step 1: Write the failing tests**

```php
<?php
// tests/Unit/Core/ResponseTest.php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Smallwork\Core\Response;

class ResponseTest extends TestCase
{
    public function test_json_response(): void
    {
        $response = Response::json(['message' => 'hello'], 200);

        $this->assertEquals(200, $response->status());
        $this->assertEquals('application/json', $response->header('Content-Type'));
        $this->assertEquals('{"message":"hello"}', $response->body());
    }

    public function test_json_with_custom_status(): void
    {
        $response = Response::json(['error' => 'not found'], 404);

        $this->assertEquals(404, $response->status());
    }

    public function test_html_response(): void
    {
        $response = Response::html('<h1>Hello</h1>', 200);

        $this->assertEquals('text/html; charset=UTF-8', $response->header('Content-Type'));
        $this->assertEquals('<h1>Hello</h1>', $response->body());
    }

    public function test_empty_response(): void
    {
        $response = Response::empty(204);

        $this->assertEquals(204, $response->status());
        $this->assertEquals('', $response->body());
    }

    public function test_redirect(): void
    {
        $response = Response::redirect('/login', 302);

        $this->assertEquals(302, $response->status());
        $this->assertEquals('/login', $response->header('Location'));
    }

    public function test_with_header(): void
    {
        $response = Response::json(['ok' => true])
            ->withHeader('X-Custom', 'value');

        $this->assertEquals('value', $response->header('X-Custom'));
    }

    public function test_with_cookie(): void
    {
        $response = Response::json(['ok' => true])
            ->withCookie('token', 'abc123', 3600);

        $cookies = $response->cookies();
        $this->assertCount(1, $cookies);
        $this->assertEquals('token', $cookies[0]['name']);
        $this->assertEquals('abc123', $cookies[0]['value']);
    }

    public function test_stream_creates_callable_response(): void
    {
        $chunks = [];
        $response = Response::stream(function (callable $write) use (&$chunks) {
            $write('chunk1');
            $write('chunk2');
        });

        $this->assertTrue($response->isStream());
    }
}
```

**Step 2: Run tests to verify they fail**

```bash
vendor/bin/phpunit tests/Unit/Core/ResponseTest.php -v
```
Expected: FAIL — class not found.

**Step 3: Write the implementation**

```php
<?php
// src/Core/Response.php

declare(strict_types=1);

namespace Smallwork\Core;

class Response
{
    private array $cookies = [];
    private bool $isStream = false;
    private ?\Closure $streamCallback = null;

    private function __construct(
        private string $body,
        private int $status,
        private array $headers,
    ) {}

    public static function json(mixed $data, int $status = 200): self
    {
        return new self(
            json_encode($data, JSON_THROW_ON_ERROR),
            $status,
            ['Content-Type' => 'application/json'],
        );
    }

    public static function html(string $html, int $status = 200): self
    {
        return new self(
            $html,
            $status,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        );
    }

    public static function empty(int $status = 204): self
    {
        return new self('', $status, []);
    }

    public static function redirect(string $url, int $status = 302): self
    {
        return new self('', $status, ['Location' => $url]);
    }

    public static function stream(callable $callback): self
    {
        $response = new self('', 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
        ]);
        $response->isStream = true;
        $response->streamCallback = $callback(...);
        return $response;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function header(string $name): ?string
    {
        foreach ($this->headers as $k => $v) {
            if (strcasecmp($k, $name) === 0) {
                return $v;
            }
        }
        return null;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;
        return $clone;
    }

    public function withCookie(string $name, string $value, int $maxAge = 0, string $path = '/', bool $httpOnly = true, bool $secure = false): self
    {
        $clone = clone $this;
        $clone->cookies[] = [
            'name' => $name,
            'value' => $value,
            'maxAge' => $maxAge,
            'path' => $path,
            'httpOnly' => $httpOnly,
            'secure' => $secure,
        ];
        return $clone;
    }

    public function cookies(): array
    {
        return $this->cookies;
    }

    public function isStream(): bool
    {
        return $this->isStream;
    }

    /**
     * Send the response to the client.
     */
    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }

        foreach ($this->cookies as $cookie) {
            setcookie(
                $cookie['name'],
                $cookie['value'],
                [
                    'expires' => $cookie['maxAge'] > 0 ? time() + $cookie['maxAge'] : 0,
                    'path' => $cookie['path'],
                    'httponly' => $cookie['httpOnly'],
                    'secure' => $cookie['secure'],
                ],
            );
        }

        if ($this->isStream && $this->streamCallback) {
            ob_end_flush();
            ($this->streamCallback)(function (string $data) {
                echo "data: $data\n\n";
                flush();
            });
        } else {
            echo $this->body;
        }
    }
}
```

**Step 4: Run tests to verify they pass**

```bash
vendor/bin/phpunit tests/Unit/Core/ResponseTest.php -v
```
Expected: All 8 tests pass.

**Step 5: Commit**

```bash
git add src/Core/Response.php tests/Unit/Core/ResponseTest.php
git commit -m "feat: add Response class with JSON, HTML, streaming, redirects"
```

---

### Task 4: DI Container

**Files:**
- Create: `src/Core/Container.php`
- Create: `tests/Unit/Core/ContainerTest.php`

**Step 1: Write the failing tests**

```php
<?php
// tests/Unit/Core/ContainerTest.php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Smallwork\Core\Container;

class ContainerTest extends TestCase
{
    public function test_bind_and_resolve(): void
    {
        $container = new Container();
        $container->bind('greeting', fn() => 'hello');

        $this->assertEquals('hello', $container->resolve('greeting'));
    }

    public function test_singleton(): void
    {
        $container = new Container();
        $count = 0;
        $container->singleton('counter', function () use (&$count) {
            $count++;
            return new \stdClass();
        });

        $a = $container->resolve('counter');
        $b = $container->resolve('counter');

        $this->assertSame($a, $b);
        $this->assertEquals(1, $count); // Factory called once
    }

    public function test_bind_instance(): void
    {
        $container = new Container();
        $obj = new \stdClass();
        $obj->name = 'test';
        $container->instance('myobj', $obj);

        $this->assertSame($obj, $container->resolve('myobj'));
    }

    public function test_has(): void
    {
        $container = new Container();
        $container->bind('exists', fn() => true);

        $this->assertTrue($container->has('exists'));
        $this->assertFalse($container->has('missing'));
    }

    public function test_throws_on_missing_binding(): void
    {
        $container = new Container();

        $this->expectException(\RuntimeException::class);
        $container->resolve('nonexistent');
    }

    public function test_autowire_constructor(): void
    {
        $container = new Container();
        $container->bind(DummyDep::class, fn() => new DummyDep('injected'));

        $resolved = $container->make(DummyService::class);

        $this->assertInstanceOf(DummyService::class, $resolved);
        $this->assertEquals('injected', $resolved->dep->value);
    }
}

// Test doubles
class DummyDep
{
    public function __construct(public string $value) {}
}

class DummyService
{
    public function __construct(public DummyDep $dep) {}
}
```

**Step 2: Run tests — expect failure**

```bash
vendor/bin/phpunit tests/Unit/Core/ContainerTest.php -v
```

**Step 3: Write the implementation**

```php
<?php
// src/Core/Container.php

declare(strict_types=1);

namespace Smallwork\Core;

class Container
{
    private array $bindings = [];
    private array $singletons = [];
    private array $instances = [];

    public function bind(string $abstract, callable $factory): void
    {
        $this->bindings[$abstract] = $factory;
    }

    public function singleton(string $abstract, callable $factory): void
    {
        $this->bindings[$abstract] = $factory;
        $this->singletons[$abstract] = true;
    }

    public function instance(string $abstract, mixed $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }

    public function resolve(string $abstract): mixed
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        if (!isset($this->bindings[$abstract])) {
            throw new \RuntimeException("No binding found for '$abstract'.");
        }

        $result = ($this->bindings[$abstract])($this);

        if (isset($this->singletons[$abstract])) {
            $this->instances[$abstract] = $result;
        }

        return $result;
    }

    /**
     * Auto-wire a class by resolving constructor dependencies.
     */
    public function make(string $class): mixed
    {
        if ($this->has($class)) {
            return $this->resolve($class);
        }

        $reflection = new \ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return new $class();
        }

        $params = [];
        foreach ($constructor->getParameters() as $param) {
            $type = $param->getType();
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $params[] = $this->resolve($type->getName());
            } elseif ($param->isDefaultValueAvailable()) {
                $params[] = $param->getDefaultValue();
            } else {
                throw new \RuntimeException(
                    "Cannot autowire parameter '{$param->getName()}' of class '$class'."
                );
            }
        }

        return $reflection->newInstanceArgs($params);
    }
}
```

**Step 4: Run tests — expect pass**

```bash
vendor/bin/phpunit tests/Unit/Core/ContainerTest.php -v
```

**Step 5: Commit**

```bash
git add src/Core/Container.php tests/Unit/Core/ContainerTest.php
git commit -m "feat: add DI Container with binding, singletons, and autowiring"
```

---

### Task 5: Router

**Files:**
- Create: `src/Core/Router.php`
- Create: `tests/Unit/Core/RouterTest.php`

**Step 1: Write the failing tests**

```php
<?php
// tests/Unit/Core/RouterTest.php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Smallwork\Core\Router;
use Smallwork\Core\Request;

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
```

**Step 2: Run tests — expect failure**

```bash
vendor/bin/phpunit tests/Unit/Core/RouterTest.php -v
```

**Step 3: Write the implementation**

```php
<?php
// src/Core/Router.php

declare(strict_types=1);

namespace Smallwork\Core;

class Router
{
    private array $routes = [];
    private string $groupPrefix = '';
    private array $groupMiddleware = [];

    public function get(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    public function post(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    public function put(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->addRoute('PUT', $path, $handler, $middleware);
    }

    public function patch(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->addRoute('PATCH', $path, $handler, $middleware);
    }

    public function delete(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    public function group(string $prefix, callable $callback, array $middleware = []): void
    {
        $previousPrefix = $this->groupPrefix;
        $previousMiddleware = $this->groupMiddleware;

        $this->groupPrefix = $previousPrefix . $prefix;
        $this->groupMiddleware = array_merge($previousMiddleware, $middleware);

        $callback($this);

        $this->groupPrefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
    }

    public function match(string $method, string $path): ?array
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $params = $this->matchPath($route['pattern'], $path);
            if ($params !== null) {
                return [
                    'handler' => $route['handler'],
                    'params' => $params,
                    'middleware' => $route['middleware'],
                ];
            }
        }

        return null;
    }

    public function routes(): array
    {
        return $this->routes;
    }

    private function addRoute(string $method, string $path, array|callable $handler, array $middleware): void
    {
        $fullPath = $this->groupPrefix . $path;
        $allMiddleware = array_merge($this->groupMiddleware, $middleware);

        $this->routes[] = [
            'method' => $method,
            'pattern' => $fullPath,
            'handler' => $handler,
            'middleware' => $allMiddleware,
        ];
    }

    private function matchPath(string $pattern, string $path): ?array
    {
        // Convert /users/{id} to regex /users/(?P<id>[^/]+)
        $regex = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $path, $matches)) {
            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }
            return $params;
        }

        return null;
    }
}
```

**Step 4: Run tests — expect pass**

```bash
vendor/bin/phpunit tests/Unit/Core/RouterTest.php -v
```

**Step 5: Commit**

```bash
git add src/Core/Router.php tests/Unit/Core/RouterTest.php
git commit -m "feat: add Router with params, groups, middleware, and HTTP methods"
```

---

### Task 6: Middleware Pipeline

**Files:**
- Create: `src/Core/Middleware/Pipeline.php`
- Create: `tests/Unit/Core/PipelineTest.php`

**Step 1: Write the failing tests**

```php
<?php
// tests/Unit/Core/PipelineTest.php

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
        $request = Request::create('GET', '/');  // No auth header

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
```

**Step 2: Run tests — expect failure**

```bash
vendor/bin/phpunit tests/Unit/Core/PipelineTest.php -v
```

**Step 3: Write the implementation**

```php
<?php
// src/Core/Middleware/Pipeline.php

declare(strict_types=1);

namespace Smallwork\Core\Middleware;

use Smallwork\Core\Request;
use Smallwork\Core\Response;

class Pipeline
{
    /**
     * Run the request through a stack of middleware, then the handler.
     *
     * @param Request $request
     * @param array $middleware Array of middleware objects with handle(Request, callable): Response
     * @param callable $handler The final request handler
     * @return Response
     */
    public function handle(Request $request, array $middleware, callable $handler): Response
    {
        $runner = $this->buildPipeline($middleware, $handler);
        return $runner($request);
    }

    private function buildPipeline(array $middleware, callable $handler): callable
    {
        $pipeline = $handler;

        // Build from inside out (last middleware wraps closest to handler)
        foreach (array_reverse($middleware) as $mw) {
            $next = $pipeline;
            $pipeline = function (Request $request) use ($mw, $next): Response {
                return $mw->handle($request, $next);
            };
        }

        return $pipeline;
    }
}
```

**Step 4: Run tests — expect pass**

```bash
vendor/bin/phpunit tests/Unit/Core/PipelineTest.php -v
```

**Step 5: Commit**

```bash
git add src/Core/Middleware/Pipeline.php tests/Unit/Core/PipelineTest.php
git commit -m "feat: add middleware Pipeline with onion-style execution"
```

---

### Task 7: App Bootstrap (Ties Everything Together)

**Files:**
- Create: `src/Core/App.php`
- Create: `src/helpers.php` (global helper functions: `env()`)
- Create: `tests/Unit/Core/AppTest.php`
- Modify: `composer.json` (add helpers autoload)

**Step 1: Write the failing tests**

```php
<?php
// tests/Unit/Core/AppTest.php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Smallwork\Core\App;
use Smallwork\Core\Router;
use Smallwork\Core\Container;
use Smallwork\Core\Request;
use Smallwork\Core\Response;

class AppTest extends TestCase
{
    private string $fixtureDir;

    protected function setUp(): void
    {
        $this->fixtureDir = sys_get_temp_dir() . '/smallwork_test_' . uniqid();
        mkdir($this->fixtureDir);
        mkdir($this->fixtureDir . '/config/routes', 0755, true);
        mkdir($this->fixtureDir . '/storage/logs', 0755, true);

        // Create minimal .env
        file_put_contents($this->fixtureDir . '/.env', "APP_NAME=TestApp\nAPP_DEBUG=true\n");

        // Create empty route files
        file_put_contents($this->fixtureDir . '/config/routes/api.php', "<?php\n// No routes\n");
        file_put_contents($this->fixtureDir . '/config/routes/web.php', "<?php\n// No routes\n");
    }

    protected function tearDown(): void
    {
        // Cleanup temp files
        $this->removeDir($this->fixtureDir);
    }

    public function test_creates_app_instance(): void
    {
        $app = App::create($this->fixtureDir);

        $this->assertInstanceOf(App::class, $app);
    }

    public function test_container_is_accessible(): void
    {
        $app = App::create($this->fixtureDir);

        $this->assertInstanceOf(Container::class, $app->container());
    }

    public function test_router_is_accessible(): void
    {
        $app = App::create($this->fixtureDir);

        $this->assertInstanceOf(Router::class, $app->router());
    }

    public function test_handles_request_to_registered_route(): void
    {
        $app = App::create($this->fixtureDir);
        $app->router()->get('/ping', function (Request $request) {
            return Response::json(['pong' => true]);
        });

        $request = Request::create('GET', '/ping');
        $response = $app->handleRequest($request);

        $this->assertEquals(200, $response->status());
        $this->assertStringContainsString('pong', $response->body());
    }

    public function test_returns_404_for_unknown_route(): void
    {
        $app = App::create($this->fixtureDir);
        $request = Request::create('GET', '/nonexistent');
        $response = $app->handleRequest($request);

        $this->assertEquals(404, $response->status());
    }

    public function test_applies_middleware_to_route(): void
    {
        $app = App::create($this->fixtureDir);

        $headerMiddleware = new class {
            public function handle(Request $request, callable $next): Response {
                $response = $next($request);
                return $response->withHeader('X-Test', 'applied');
            }
        };

        $app->container()->instance('TestMiddleware', $headerMiddleware);

        $app->router()->get('/test', function (Request $request) {
            return Response::json(['ok' => true]);
        }, middleware: ['TestMiddleware']);

        $request = Request::create('GET', '/test');
        $response = $app->handleRequest($request);

        $this->assertEquals('applied', $response->header('X-Test'));
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = "$dir/$item";
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
```

**Step 2: Run tests — expect failure**

```bash
vendor/bin/phpunit tests/Unit/Core/AppTest.php -v
```

**Step 3: Create `src/helpers.php`**

```php
<?php
// src/helpers.php

declare(strict_types=1);

if (!function_exists('env')) {
    /**
     * Get an environment variable with optional default.
     */
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? getenv($key);
        if ($value === false) {
            return $default;
        }

        // Cast common string values
        return match (strtolower((string) $value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            'empty', '(empty)' => '',
            default => $value,
        };
    }
}
```

**Step 4: Update `composer.json` to autoload helpers**

Add to the `autoload` section:
```json
"files": ["src/helpers.php"]
```

Run: `composer dump-autoload`

**Step 5: Write the App class**

```php
<?php
// src/Core/App.php

declare(strict_types=1);

namespace Smallwork\Core;

use Smallwork\Core\Middleware\Pipeline;

class App
{
    private Container $container;
    private Router $router;
    private Pipeline $pipeline;
    private array $globalMiddleware = [];

    private function __construct(private string $basePath)
    {
        $this->container = new Container();
        $this->router = new Router();
        $this->pipeline = new Pipeline();

        $this->container->instance(self::class, $this);
        $this->container->instance(Router::class, $this->router);
        $this->container->instance(Container::class, $this->container);

        $this->loadEnv();
        $this->loadRoutes();
    }

    public static function create(string $basePath): self
    {
        return new self($basePath);
    }

    public function container(): Container
    {
        return $this->container;
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function basePath(string $path = ''): string
    {
        return $this->basePath . ($path ? '/' . ltrim($path, '/') : '');
    }

    public function addMiddleware(string|object $middleware): void
    {
        $this->globalMiddleware[] = $middleware;
    }

    /**
     * Handle an incoming request and return a response.
     */
    public function handleRequest(Request $request): Response
    {
        $match = $this->router->match($request->method(), $request->path());

        if ($match === null) {
            return Response::json(['error' => 'Not Found'], 404);
        }

        $request->setRouteParams($match['params']);

        // Resolve middleware instances
        $middlewareStack = [];
        $allMiddleware = array_merge($this->globalMiddleware, $match['middleware']);
        foreach ($allMiddleware as $mw) {
            if (is_string($mw)) {
                $middlewareStack[] = $this->container->resolve($mw);
            } else {
                $middlewareStack[] = $mw;
            }
        }

        // Build the handler
        $handler = $match['handler'];
        $container = $this->container;

        $finalHandler = function (Request $request) use ($handler, $container): Response {
            if (is_callable($handler)) {
                return $handler($request);
            }

            // [ControllerClass, 'method'] format
            [$class, $method] = $handler;
            $controller = $container->make($class);
            return $controller->$method($request);
        };

        return $this->pipeline->handle($request, $middlewareStack, $finalHandler);
    }

    /**
     * Run the application (for web requests).
     */
    public function run(): void
    {
        $request = Request::capture();
        $response = $this->handleRequest($request);
        $response->send();
    }

    /**
     * Run CLI commands (stub for now).
     */
    public function runCli(array $argv): void
    {
        // Will be implemented in Console phase
        echo "Smallwork CLI - coming soon\n";
    }

    private function loadEnv(): void
    {
        $envFile = $this->basePath('.env');
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                if (str_contains($line, '=')) {
                    [$key, $value] = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    $_ENV[$key] = $value;
                    putenv("$key=$value");
                }
            }
        }
    }

    private function loadRoutes(): void
    {
        $router = $this->router;

        $apiRoutes = $this->basePath('config/routes/api.php');
        if (file_exists($apiRoutes)) {
            require $apiRoutes;
        }

        $webRoutes = $this->basePath('config/routes/web.php');
        if (file_exists($webRoutes)) {
            require $webRoutes;
        }
    }
}
```

**Step 6: Run tests — expect pass**

```bash
vendor/bin/phpunit tests/Unit/Core/AppTest.php -v
```

**Step 7: Run full test suite**

```bash
vendor/bin/phpunit -v
```
Expected: All tests pass across all test files.

**Step 8: Commit**

```bash
git add src/Core/App.php src/helpers.php tests/Unit/Core/AppTest.php composer.json
git commit -m "feat: add App bootstrap, env loading, request handling, and helpers"
```

---

### Task 8: Built-in CORS Middleware

**Files:**
- Create: `src/Core/Middleware/CorsMiddleware.php`
- Create: `tests/Unit/Core/CorsMiddlewareTest.php`

**Step 1: Write the failing tests**

```php
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
        $request = Request::create('OPTIONS', '/api/data', headers: [
            'Origin' => 'https://example.com',
            'Access-Control-Request-Method' => 'POST',
        ]);

        $response = $cors->handle($request, fn($r) => Response::json([]));

        $this->assertEquals(204, $response->status());
        $this->assertEquals('https://example.com', $response->header('Access-Control-Allow-Origin'));
    }

    public function test_rejects_disallowed_origin(): void
    {
        $cors = new CorsMiddleware(['https://allowed.com']);
        $request = Request::create('GET', '/api/data', headers: [
            'Origin' => 'https://evil.com',
        ]);

        $response = $cors->handle($request, fn($r) => Response::json(['ok' => true]));

        $this->assertNull($response->header('Access-Control-Allow-Origin'));
    }
}
```

**Step 2: Run tests — expect failure**

**Step 3: Write the implementation**

```php
<?php
// src/Core/Middleware/CorsMiddleware.php

declare(strict_types=1);

namespace Smallwork\Core\Middleware;

use Smallwork\Core\Request;
use Smallwork\Core\Response;

class CorsMiddleware
{
    public function __construct(
        private array $allowedOrigins = ['*'],
        private array $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        private array $allowedHeaders = ['Content-Type', 'Authorization', 'X-API-Key'],
        private int $maxAge = 86400,
    ) {}

    public function handle(Request $request, callable $next): Response
    {
        $origin = $request->header('Origin');
        $allowed = $this->isOriginAllowed($origin);

        // Handle preflight
        if ($request->method() === 'OPTIONS') {
            $response = Response::empty(204);
        } else {
            $response = $next($request);
        }

        if ($allowed) {
            $allowOrigin = in_array('*', $this->allowedOrigins) ? '*' : $origin;
            $response = $response
                ->withHeader('Access-Control-Allow-Origin', $allowOrigin)
                ->withHeader('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods))
                ->withHeader('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders))
                ->withHeader('Access-Control-Max-Age', (string) $this->maxAge);
        }

        return $response;
    }

    private function isOriginAllowed(?string $origin): bool
    {
        if (in_array('*', $this->allowedOrigins)) {
            return true;
        }
        if ($origin === null) {
            return false;
        }
        return in_array($origin, $this->allowedOrigins);
    }
}
```

**Step 4: Run tests — expect pass**

```bash
vendor/bin/phpunit tests/Unit/Core/CorsMiddlewareTest.php -v
```

**Step 5: Commit**

```bash
git add src/Core/Middleware/CorsMiddleware.php tests/Unit/Core/CorsMiddlewareTest.php
git commit -m "feat: add CORS middleware with preflight and origin validation"
```

---

### Task 9: Rate Limiting Middleware (In-Memory for Phase 1)

**Files:**
- Create: `src/Core/Middleware/RateLimitMiddleware.php`
- Create: `tests/Unit/Core/RateLimitMiddlewareTest.php`

**Step 1: Write the failing tests**

```php
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

        // Third request should be blocked
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

        $this->assertEquals(200, $response->status()); // Different IP, not limited
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
```

**Step 2: Run tests — expect failure**

**Step 3: Write the implementation**

```php
<?php
// src/Core/Middleware/RateLimitMiddleware.php

declare(strict_types=1);

namespace Smallwork\Core\Middleware;

use Smallwork\Core\Request;
use Smallwork\Core\Response;

class RateLimitMiddleware
{
    /** @var array<string, array{count: int, resetAt: float}> */
    private static array $store = [];

    public function __construct(
        private int $maxRequests = 60,
        private int $windowSeconds = 60,
    ) {}

    public function handle(Request $request, callable $next): Response
    {
        $key = $this->resolveKey($request);
        $now = microtime(true);

        // Initialize or reset expired window
        if (!isset(self::$store[$key]) || $now >= self::$store[$key]['resetAt']) {
            self::$store[$key] = [
                'count' => 0,
                'resetAt' => $now + $this->windowSeconds,
            ];
        }

        self::$store[$key]['count']++;
        $remaining = max(0, $this->maxRequests - self::$store[$key]['count']);

        if (self::$store[$key]['count'] > $this->maxRequests) {
            return Response::json([
                'error' => 'Too Many Requests',
                'retry_after' => (int) ceil(self::$store[$key]['resetAt'] - $now),
            ], 429)
                ->withHeader('X-RateLimit-Limit', (string) $this->maxRequests)
                ->withHeader('X-RateLimit-Remaining', '0')
                ->withHeader('Retry-After', (string) (int) ceil(self::$store[$key]['resetAt'] - $now));
        }

        $response = $next($request);

        return $response
            ->withHeader('X-RateLimit-Limit', (string) $this->maxRequests)
            ->withHeader('X-RateLimit-Remaining', (string) $remaining);
    }

    private function resolveKey(Request $request): string
    {
        return $request->header('X-Forwarded-For')
            ?? $request->header('X-Real-Ip')
            ?? 'unknown';
    }

    /**
     * Reset the store (useful for testing).
     */
    public static function reset(): void
    {
        self::$store = [];
    }
}
```

**Step 4: Run tests — expect pass**

```bash
vendor/bin/phpunit tests/Unit/Core/RateLimitMiddlewareTest.php -v
```

**Step 5: Commit**

```bash
git add src/Core/Middleware/RateLimitMiddleware.php tests/Unit/Core/RateLimitMiddlewareTest.php
git commit -m "feat: add rate limiting middleware with per-IP tracking"
```

---

### Task 10: Integration Test — Full Request Lifecycle

**Files:**
- Create: `tests/Integration/HttpTest.php`

**Step 1: Write the integration test**

```php
<?php
// tests/Integration/HttpTest.php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Smallwork\Core\App;
use Smallwork\Core\Request;
use Smallwork\Core\Response;
use Smallwork\Core\Middleware\CorsMiddleware;

class HttpTest extends TestCase
{
    private App $app;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/sw_integration_' . uniqid();
        mkdir($this->tempDir);
        mkdir($this->tempDir . '/config/routes', 0755, true);
        mkdir($this->tempDir . '/storage/logs', 0755, true);
        file_put_contents($this->tempDir . '/.env', "APP_NAME=IntegrationTest\n");
        file_put_contents($this->tempDir . '/config/routes/api.php', "<?php\n");
        file_put_contents($this->tempDir . '/config/routes/web.php', "<?php\n");

        $this->app = App::create($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    public function test_full_json_api_request(): void
    {
        $this->app->router()->post('/api/echo', function (Request $request) {
            return Response::json([
                'received' => $request->json('message'),
                'method' => $request->method(),
            ]);
        });

        $request = Request::create(
            'POST', '/api/echo',
            body: '{"message":"hello world"}',
            headers: ['Content-Type' => 'application/json'],
        );

        $response = $this->app->handleRequest($request);

        $this->assertEquals(200, $response->status());
        $data = json_decode($response->body(), true);
        $this->assertEquals('hello world', $data['received']);
        $this->assertEquals('POST', $data['method']);
    }

    public function test_route_params_passed_to_handler(): void
    {
        $this->app->router()->get('/users/{id}', function (Request $request) {
            return Response::json(['user_id' => $request->param('id')]);
        });

        $request = Request::create('GET', '/users/42');
        $response = $this->app->handleRequest($request);

        $data = json_decode($response->body(), true);
        $this->assertEquals('42', $data['user_id']);
    }

    public function test_middleware_chain_with_cors(): void
    {
        $cors = new CorsMiddleware(['https://myapp.com']);
        $this->app->container()->instance('cors', $cors);

        $this->app->router()->get('/api/data', function (Request $request) {
            return Response::json(['data' => 'secret']);
        }, middleware: ['cors']);

        $request = Request::create('GET', '/api/data', headers: ['Origin' => 'https://myapp.com']);
        $response = $this->app->handleRequest($request);

        $this->assertEquals(200, $response->status());
        $this->assertEquals('https://myapp.com', $response->header('Access-Control-Allow-Origin'));
    }

    public function test_404_for_missing_route(): void
    {
        $request = Request::create('GET', '/this/does/not/exist');
        $response = $this->app->handleRequest($request);

        $this->assertEquals(404, $response->status());
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = "$dir/$item";
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
```

**Step 2: Run full test suite**

```bash
vendor/bin/phpunit -v
```
Expected: ALL tests pass (Unit + Integration).

**Step 3: Commit**

```bash
git add tests/Integration/HttpTest.php
git commit -m "test: add integration tests for full HTTP request lifecycle"
```

---

### Task 11: Cleanup Legacy Files & Update README

**Files:**
- Archive: `index.php` (rename to `legacy/index.php`)
- Archive: `router.php` (rename to `legacy/router.php`)
- Archive: `config/` old files (move to `legacy/config/`)
- Archive: `includes/` (move to `legacy/includes/`)
- Archive: `app/controllers/` old files (move to `legacy/app/`)
- Update: `README.md`

**Step 1: Move legacy files**

```bash
mkdir -p legacy/config legacy/app legacy/includes
mv index.php legacy/
mv router.php legacy/
mv config/bootstrap.php config/config.php config/config.ini config/routes.php legacy/config/
mv includes/autoload.php legacy/includes/
# Move old controllers
cp -r app/controllers legacy/app/ 2>/dev/null || true
cp -r app/responders legacy/app/ 2>/dev/null || true
rm -rf app/controllers/api app/controllers/web app/responders 2>/dev/null || true
```

**Step 2: Create config route files**

```php
<?php
// config/routes/api.php
// Define your API routes here.
// Example:
// $router->group('/api/v1', function ($router) {
//     $router->get('/status', function (\Smallwork\Core\Request $r) {
//         return \Smallwork\Core\Response::json(['status' => 'ok']);
//     });
// });
```

```php
<?php
// config/routes/web.php
// Define your web routes here.
// Example:
// $router->get('/', function (\Smallwork\Core\Request $r) {
//     return \Smallwork\Core\Response::html('<h1>Welcome to Smallwork</h1>');
// });
```

**Step 3: Update README.md**

```markdown
# Smallwork

A small footprint full-stack AI framework for PHP.

## Requirements

- PHP 8.2+
- Composer

## Quick Start

```bash
composer install
php -S localhost:8000 -t public
```

## Project Structure

- `src/` — Framework source code
- `app/` — Your application code (controllers, models, views)
- `config/` — Configuration and route definitions
- `public/` — Web root (entry point)
- `tests/` — PHPUnit tests
- `storage/` — Logs, cache, database files

## Running Tests

```bash
vendor/bin/phpunit
```

## Configuration

Copy `.env.example` to `.env` and configure your settings.
```

**Step 4: Run full test suite to ensure nothing broke**

```bash
vendor/bin/phpunit -v
```

**Step 5: Commit**

```bash
git add -A
git commit -m "refactor: archive legacy files, add route configs, update README"
```

---

## Phase 2: Database Layer (High-Level)

> Detailed TDD plan will be written when Phase 1 is complete.

### Task 12: PDO Connection Factory & Adapter
- `src/Database/Connection.php` — factory that reads `config/database.php` and returns PDO
- `src/Database/Adapters/PdoAdapter.php` — wraps PDO with convenience methods
- SQLite, MySQL, PostgreSQL support via DSN switching
- Tests with SQLite in-memory

### Task 13: Query Builder
- `src/Database/QueryBuilder.php` — fluent interface
- `select()`, `where()`, `orderBy()`, `limit()`, `insert()`, `update()`, `delete()`
- `join()`, `groupBy()`, `having()`
- Transaction support: `beginTransaction()`, `commit()`, `rollback()`
- Tests with SQLite in-memory

### Task 14: Migration System
- `src/Database/Migration.php` — base class with `up()` / `down()`
- `src/Database/Schema.php` — table builder (create, alter, drop)
- CLI command: `php smallwork migrate`, `php smallwork migrate:rollback`
- Migration tracking via `_migrations` table

### Task 15: Redis Adapter
- `src/Database/Adapters/RedisAdapter.php` — wraps phpredis or predis
- `get()`, `set()`, `delete()`, `increment()`, `expire()`
- Used by rate limiter and cache
- Config via `config/database.php` redis section

### Task 16: Qdrant Vector Store Adapter
- `src/Database/Adapters/QdrantAdapter.php` — HTTP client to Qdrant API
- Implements `VectorStoreInterface`
- `createCollection()`, `upsert()`, `search()`, `delete()`

### Task 17: pgvector Adapter
- `src/Database/Adapters/PgvectorAdapter.php` — uses PDO PostgreSQL with pgvector extension
- Implements `VectorStoreInterface`
- Same interface as Qdrant, different backend

---

## Phase 3: Authentication & Security (High-Level)

### Task 18: JWT Authentication
- `src/Auth/JwtAuth.php` — encode/decode JWT tokens (HS256)
- No external library — PHP's `hash_hmac` + base64
- Token issuance, verification, expiry, refresh

### Task 19: API Key Authentication
- `src/Auth/ApiKeyAuth.php` — generate, hash, verify API keys
- Keys stored in database (hashed with `password_hash`)
- Per-key metadata (name, permissions, rate limits)

### Task 20: Auth Middleware
- `src/Core/Middleware/AuthMiddleware.php` — checks JWT or API key
- Configurable strategy per route group
- Sets `$request->user()` on successful auth

### Task 21: Role-Based Access Control
- `src/Auth/RoleManager.php` — roles and permissions
- Config-driven role definitions
- `RoleMiddleware::require('admin')` middleware

### Task 22: Input Validation
- `src/Core/Validator.php` — rule-based validation
- Rules: `required`, `string`, `email`, `min`, `max`, `in`, `numeric`
- Returns structured error responses

---

## Phase 4: View & Frontend Layer (High-Level)

### Task 23: Template Engine
- `src/View/Engine.php` — Blade-like compiler
- `{{ }}` escaped, `{!! !!}` raw, `@if/@foreach/@extends/@section/@yield/@include`
- Template files: `app/Views/*.sw.php`
- Compiled template caching in `storage/cache/views/`

### Task 24: HTMX Helpers
- `src/View/HtmxHelper.php` — response helpers for HTMX
- `Response::htmx($html)` — partial HTML response
- SSE streaming helpers for AI chat interfaces
- Trigger headers, swap targets

### Task 25: Layout and Asset Pipeline
- Default layout: `app/Views/layouts/app.sw.php`
- Static asset serving from `public/assets/`
- CSS/JS includes via template helpers

---

## Phase 5: AI Layer (High-Level)

### Task 26: AI Provider Interface & Gateway
- `src/AI/Providers/ProviderInterface.php` — `chat()`, `embed()`, `stream()`
- `src/AI/Gateway.php` — resolves provider from config, delegates calls
- Config: `config/ai.php` with provider credentials and defaults

### Task 27: OpenAI Provider
- `src/AI/Providers/OpenAIProvider.php`
- Chat completions, embeddings, streaming via cURL
- OpenAI-compatible format (also works with any OpenAI-compatible endpoint)

### Task 28: Anthropic Provider
- `src/AI/Providers/AnthropicProvider.php`
- Translates OpenAI message format to Anthropic's `messages` API
- Handles `anthropic-version` header, system prompt extraction

### Task 29: Grok Provider
- `src/AI/Providers/GrokProvider.php`
- xAI API (OpenAI-compatible format)
- Minimal adapter

### Task 30: Chat Service
- `src/AI/Chat.php` — manages conversations
- Message history, system prompts, streaming
- Token usage tracking
- Conversation persistence (DB)

### Task 31: Embeddings Service
- `src/AI/Embeddings.php` — generate embeddings from text
- Batch embedding support
- Auto-chunking for long text

### Task 32: Semantic Search
- `src/AI/SemanticSearch.php` — orchestrates embed → search → format
- RAG helper: inject search results into chat context
- Configurable vector store backend (Qdrant or pgvector)

### Task 33: Content Moderation Middleware
- `src/AI/Middleware/ContentModeration.php`
- Classifies request content via AI
- Blocks harmful content with 422 response

### Task 34: Intent Classification Middleware
- `src/AI/Middleware/IntentClassifier.php`
- Classifies user intent, adds `$request->getAttribute('intent')`
- Configurable intent categories

### Task 35: Auto-Summarizer Middleware
- `src/AI/Middleware/AutoSummarizer.php`
- Summarizes long inputs, adds `$request->getAttribute('summary')`
- Configurable length threshold

### Task 36: Prompt Template Engine
- `src/AI/Prompts/TemplateEngine.php` — `{{variable}}` substitution
- Load from `app/Prompts/{name}.v{version}.prompt` files
- `Prompt::render()`, `Prompt::latest()`, `Prompt::version()`

### Task 37: Prompt Version Manager
- `src/AI/Prompts/VersionManager.php`
- Discovers prompt versions from filesystem
- Supports A/B testing by selecting versions

---

## Phase 6: Enterprise Features (High-Level)

### Task 38: Logger
- `src/Core/Logger.php` — PSR-3-compatible
- JSON structured output to `storage/logs/`
- Log levels: debug, info, warning, error, critical
- Request context (method, path, duration)

### Task 39: Health Check Endpoint
- Built-in `/health` route
- Checks: DB, Redis, AI provider connectivity
- Returns JSON with component status and latency

### Task 40: CLI Framework
- `src/Console/CLI.php` — command registry and dispatcher
- `src/Console/ServeCommand.php` — `php smallwork serve`
- `src/Console/MigrateCommand.php` — `php smallwork migrate`
- `src/Console/MakeCommand.php` — `php smallwork make:controller`, `make:model`, `make:migration`

### Task 41: OpenAPI Spec Generation
- Scans route definitions + PHPDoc annotations
- Generates OpenAPI 3.0 YAML/JSON
- Serves at `/api/docs`

### Task 42: Test Helpers
- `src/Testing/TestCase.php` — base test class with app bootstrapping
- `src/Testing/AIMock.php` — mock AI responses for testing
- Database transaction wrapping for test isolation

---

## Summary

| Phase | Tasks | Description |
|-------|-------|-------------|
| 1 | 1-11 | Core framework: Request, Response, Router, Middleware, DI, App |
| 2 | 12-17 | Database: PDO, QueryBuilder, Migrations, Redis, Vector stores |
| 3 | 18-22 | Auth: JWT, API keys, RBAC, validation |
| 4 | 23-25 | Views: Template engine, HTMX, layouts |
| 5 | 26-37 | AI: Gateway, providers, chat, embeddings, search, middleware, prompts |
| 6 | 38-42 | Enterprise: Logging, health, CLI, OpenAPI, test helpers |

Total: 42 tasks across 6 phases. Each phase produces a working, tested framework.
