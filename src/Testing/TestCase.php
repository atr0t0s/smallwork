<?php
// src/Testing/TestCase.php
declare(strict_types=1);
namespace Smallwork\Testing;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Smallwork\Core\App;
use Smallwork\Core\Request;
use Smallwork\Core\Response;

class TestCase extends PHPUnitTestCase
{
    protected string $fixtureDir;
    protected ?App $app = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtureDir = sys_get_temp_dir() . '/smallwork_test_' . uniqid();
        mkdir($this->fixtureDir);
        mkdir($this->fixtureDir . '/config/routes', 0755, true);
        mkdir($this->fixtureDir . '/storage/logs', 0755, true);
        file_put_contents($this->fixtureDir . '/.env', "APP_NAME=TestApp\nAPP_DEBUG=true\n");
        file_put_contents($this->fixtureDir . '/config/routes/api.php', "<?php\n// No routes\n");
        file_put_contents($this->fixtureDir . '/config/routes/web.php', "<?php\n// No routes\n");
    }

    public function createApp(): App
    {
        $this->app = App::create($this->fixtureDir);
        return $this->app;
    }

    public function get(string $path): Response
    {
        $app = $this->app ?? $this->createApp();
        $request = Request::create('GET', $path);
        return $app->handleRequest($request);
    }

    public function post(string $path, array $data = []): Response
    {
        $app = $this->app ?? $this->createApp();
        $request = Request::create('POST', $path, post: $data);
        return $app->handleRequest($request);
    }

    public function json(string $method, string $path, array $data = []): Response
    {
        $app = $this->app ?? $this->createApp();
        $body = !empty($data) ? json_encode($data, JSON_THROW_ON_ERROR) : '';
        $request = Request::create(
            method: strtoupper($method),
            path: $path,
            body: $body,
            headers: ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
        );
        return $app->handleRequest($request);
    }

    protected function tearDown(): void
    {
        $this->app = null;

        if (isset($this->fixtureDir) && is_dir($this->fixtureDir)) {
            $this->removeDir($this->fixtureDir);
        }

        parent::tearDown();
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = "$dir/$item";
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
