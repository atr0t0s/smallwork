<?php

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
        file_put_contents($this->fixtureDir . '/.env', "APP_NAME=TestApp\nAPP_DEBUG=true\n");
        file_put_contents($this->fixtureDir . '/config/routes/api.php', "<?php\n// No routes\n");
        file_put_contents($this->fixtureDir . '/config/routes/web.php', "<?php\n// No routes\n");
    }

    protected function tearDown(): void
    {
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
