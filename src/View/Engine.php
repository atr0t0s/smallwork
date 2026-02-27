<?php
// src/View/Engine.php
declare(strict_types=1);
namespace Smallwork\View;

class Engine
{
    public function __construct(
        private string $viewsPath,
        private string $cachePath,
    ) {}

    public function render(string $view, array $data = []): string
    {
        $content = $this->loadView($view);

        // Handle @extends
        $layout = null;
        $sections = [];
        if (preg_match('/@extends\("([^"]+)"\)/', $content, $m)) {
            $layout = $m[1];
            $content = str_replace($m[0], '', $content);

            // Extract @section ... @endsection
            preg_match_all('/@section\("([^"]+)"\)(.*?)@endsection/s', $content, $sectionMatches);
            for ($i = 0; $i < count($sectionMatches[0]); $i++) {
                $sections[$sectionMatches[1][$i]] = $sectionMatches[2][$i];
            }
        }

        if ($layout !== null) {
            $layoutContent = $this->loadView($layout);
            // Replace @yield with section content
            $content = preg_replace_callback('/@yield\("([^"]+)"\)/', function ($m) use ($sections) {
                return $sections[$m[1]] ?? '';
            }, $layoutContent);
        }

        // Handle @include
        $content = preg_replace_callback('/@include\("([^"]+)"\)/', function ($m) {
            return $this->loadView($m[1]);
        }, $content);

        // Compile template directives to PHP
        $compiled = $this->compile($content);

        // Write to cache and execute
        $cacheFile = $this->cachePath . '/' . md5($view . $compiled) . '.php';
        file_put_contents($cacheFile, $compiled);

        return $this->evaluate($cacheFile, $data);
    }

    private function loadView(string $name): string
    {
        $path = $this->viewsPath . '/' . str_replace('.', '/', $name) . '.sw.php';
        if (!file_exists($path)) {
            throw new \RuntimeException("View not found: $name ($path)");
        }
        return file_get_contents($path);
    }

    private function compile(string $template): string
    {
        // Order matters: raw before escaped

        // {!! $var !!} -> raw output
        $template = preg_replace('/\{!!\s*(.+?)\s*!!\}/', '<?php echo $1; ?>', $template);

        // {{ $var }} -> escaped output
        $template = preg_replace('/\{\{\s*(.+?)\s*\}\}/', '<?php echo htmlspecialchars((string)($1), ENT_QUOTES, \'UTF-8\'); ?>', $template);

        // @if($cond)
        $template = preg_replace('/@if\((.+?)\)/', '<?php if($1): ?>', $template);
        $template = str_replace('@elseif', '<?php elseif', $template);
        $template = str_replace('@else', '<?php else: ?>', $template);
        $template = str_replace('@endif', '<?php endif; ?>', $template);

        // @foreach($items as $item)
        $template = preg_replace('/@foreach\((.+?)\)/', '<?php foreach($1): ?>', $template);
        $template = str_replace('@endforeach', '<?php endforeach; ?>', $template);

        // @for
        $template = preg_replace('/@for\((.+?)\)/', '<?php for($1): ?>', $template);
        $template = str_replace('@endfor', '<?php endfor; ?>', $template);

        // @while
        $template = preg_replace('/@while\((.+?)\)/', '<?php while($1): ?>', $template);
        $template = str_replace('@endwhile', '<?php endwhile; ?>', $template);

        return $template;
    }

    private function evaluate(string $cacheFile, array $data): string
    {
        extract($data);
        ob_start();
        require $cacheFile;
        return ob_get_clean();
    }
}
