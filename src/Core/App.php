<?php

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

    public function container(): Container { return $this->container; }
    public function router(): Router { return $this->router; }

    public function basePath(string $path = ''): string
    {
        return $this->basePath . ($path ? '/' . ltrim($path, '/') : '');
    }

    public function addMiddleware(string|object $middleware): void
    {
        $this->globalMiddleware[] = $middleware;
    }

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

        $handler = $match['handler'];
        $container = $this->container;

        $finalHandler = function (Request $request) use ($handler, $container): Response {
            if (is_callable($handler)) {
                return $handler($request);
            }
            [$class, $method] = $handler;
            $controller = $container->make($class);
            return $controller->$method($request);
        };

        return $this->pipeline->handle($request, $middlewareStack, $finalHandler);
    }

    public function run(): void
    {
        $request = Request::capture();
        $response = $this->handleRequest($request);
        $response->send();
    }

    public function runCli(array $argv): void
    {
        echo "Smallwork CLI - coming soon\n";
    }

    private function loadEnv(): void
    {
        $envFile = $this->basePath('.env');
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) continue;
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
