<?php
// tests/Unit/View/ViewResponseTest.php
declare(strict_types=1);
namespace Tests\Unit\View;

use PHPUnit\Framework\TestCase;
use Smallwork\View\ViewResponse;
use Smallwork\View\Engine;
use Smallwork\Core\Response;

class ViewResponseTest extends TestCase
{
    private ViewResponse $view;
    private string $viewsDir;
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->viewsDir = sys_get_temp_dir() . '/sw_vr_views_' . uniqid();
        $this->cacheDir = sys_get_temp_dir() . '/sw_vr_cache_' . uniqid();
        mkdir($this->viewsDir, 0755, true);
        mkdir($this->cacheDir, 0755, true);

        $engine = new Engine($this->viewsDir, $this->cacheDir);
        $this->view = new ViewResponse($engine);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->viewsDir);
        $this->removeDir($this->cacheDir);
    }

    public function test_renders_view_to_response(): void
    {
        file_put_contents($this->viewsDir . '/hello.sw.php', '<h1>Hello {{ $name }}</h1>');

        $response = $this->view->make('hello', ['name' => 'World']);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->status());
        $this->assertEquals('<h1>Hello World</h1>', $response->body());
        $this->assertEquals('text/html; charset=UTF-8', $response->header('Content-Type'));
    }

    public function test_renders_with_custom_status(): void
    {
        file_put_contents($this->viewsDir . '/error.sw.php', '<h1>Not Found</h1>');
        $response = $this->view->make('error', [], 404);
        $this->assertEquals(404, $response->status());
    }

    public function test_renders_with_layout(): void
    {
        mkdir($this->viewsDir . '/layouts', 0755, true);
        file_put_contents($this->viewsDir . '/layouts/app.sw.php',
            '<html><body>@yield("content")</body></html>');
        file_put_contents($this->viewsDir . '/page.sw.php',
            '@extends("layouts.app")@section("content")<h1>Page</h1>@endsection');

        $response = $this->view->make('page');
        $this->assertEquals('<html><body><h1>Page</h1></body></html>', $response->body());
    }

    public function test_throws_on_missing_view(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->view->make('nonexistent');
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
