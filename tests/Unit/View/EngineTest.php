<?php
// tests/Unit/View/EngineTest.php
declare(strict_types=1);
namespace Tests\Unit\View;

use PHPUnit\Framework\TestCase;
use Smallwork\View\Engine;

class EngineTest extends TestCase
{
    private Engine $engine;
    private string $viewsDir;
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->viewsDir = sys_get_temp_dir() . '/sw_views_' . uniqid();
        $this->cacheDir = sys_get_temp_dir() . '/sw_cache_' . uniqid();
        mkdir($this->viewsDir, 0755, true);
        mkdir($this->cacheDir, 0755, true);
        $this->engine = new Engine($this->viewsDir, $this->cacheDir);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->viewsDir);
        $this->removeDir($this->cacheDir);
    }

    public function test_renders_plain_html(): void
    {
        file_put_contents($this->viewsDir . '/hello.sw.php', '<h1>Hello World</h1>');
        $result = $this->engine->render('hello');
        $this->assertEquals('<h1>Hello World</h1>', $result);
    }

    public function test_escaped_output(): void
    {
        file_put_contents($this->viewsDir . '/escaped.sw.php', '<p>{{ $name }}</p>');
        $result = $this->engine->render('escaped', ['name' => '<script>xss</script>']);
        $this->assertEquals('<p>&lt;script&gt;xss&lt;/script&gt;</p>', $result);
    }

    public function test_raw_output(): void
    {
        file_put_contents($this->viewsDir . '/raw.sw.php', '<div>{!! $html !!}</div>');
        $result = $this->engine->render('raw', ['html' => '<strong>bold</strong>']);
        $this->assertEquals('<div><strong>bold</strong></div>', $result);
    }

    public function test_if_directive(): void
    {
        file_put_contents($this->viewsDir . '/cond.sw.php',
            '@if($show)<p>Visible</p>@endif');
        $this->assertEquals('<p>Visible</p>', $this->engine->render('cond', ['show' => true]));
        $this->assertEquals('', $this->engine->render('cond', ['show' => false]));
    }

    public function test_if_else_directive(): void
    {
        file_put_contents($this->viewsDir . '/ifelse.sw.php',
            '@if($admin)<p>Admin</p>@else<p>User</p>@endif');
        $this->assertEquals('<p>Admin</p>', $this->engine->render('ifelse', ['admin' => true]));
        $this->assertEquals('<p>User</p>', $this->engine->render('ifelse', ['admin' => false]));
    }

    public function test_foreach_directive(): void
    {
        file_put_contents($this->viewsDir . '/loop.sw.php',
            '@foreach($items as $item)<li>{{ $item }}</li>@endforeach');
        $result = $this->engine->render('loop', ['items' => ['A', 'B', 'C']]);
        $this->assertEquals('<li>A</li><li>B</li><li>C</li>', $result);
    }

    public function test_extends_and_section(): void
    {
        // Layout
        mkdir($this->viewsDir . '/layouts', 0755, true);
        file_put_contents($this->viewsDir . '/layouts/app.sw.php',
            '<html><body>@yield("content")</body></html>');

        // Child
        file_put_contents($this->viewsDir . '/page.sw.php',
            '@extends("layouts.app")@section("content")<h1>Page</h1>@endsection');

        $result = $this->engine->render('page');
        $this->assertEquals('<html><body><h1>Page</h1></body></html>', $result);
    }

    public function test_include_directive(): void
    {
        mkdir($this->viewsDir . '/partials', 0755, true);
        file_put_contents($this->viewsDir . '/partials/nav.sw.php', '<nav>Menu</nav>');
        file_put_contents($this->viewsDir . '/page2.sw.php',
            '@include("partials.nav")<main>Content</main>');

        $result = $this->engine->render('page2');
        $this->assertEquals('<nav>Menu</nav><main>Content</main>', $result);
    }

    public function test_variables_in_included_partials(): void
    {
        mkdir($this->viewsDir . '/partials', 0755, true);
        file_put_contents($this->viewsDir . '/partials/greeting.sw.php', '<p>Hello {{ $name }}</p>');
        file_put_contents($this->viewsDir . '/greet.sw.php',
            '@include("partials.greeting")');

        $result = $this->engine->render('greet', ['name' => 'Alice']);
        $this->assertEquals('<p>Hello Alice</p>', $result);
    }

    public function test_nested_directory_view(): void
    {
        mkdir($this->viewsDir . '/admin', 0755, true);
        file_put_contents($this->viewsDir . '/admin/dashboard.sw.php', '<h1>Dashboard</h1>');
        $result = $this->engine->render('admin.dashboard');
        $this->assertEquals('<h1>Dashboard</h1>', $result);
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
