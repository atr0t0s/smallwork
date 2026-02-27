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

        $request = Request::create('POST', '/api/echo',
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
