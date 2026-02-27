<?php

declare(strict_types=1);

namespace Smallwork\Core;

class OpenApiGenerator
{
    public function __construct(
        private Router $router,
        private string $title = 'API',
        private string $version = '1.0.0',
        private string $description = '',
    ) {}

    public function generate(): array
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => $this->buildInfo(),
            'paths' => $this->buildPaths(),
        ];

        return $spec;
    }

    public function toJson(): string
    {
        return json_encode($this->generate(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function buildInfo(): array
    {
        $info = [
            'title' => $this->title,
            'version' => $this->version,
        ];

        if ($this->description !== '') {
            $info['description'] = $this->description;
        }

        return $info;
    }

    private function buildPaths(): array
    {
        $paths = [];

        foreach ($this->router->routes() as $route) {
            $pattern = $route['pattern'];
            $method = strtolower($route['method']);

            if (!isset($paths[$pattern])) {
                $paths[$pattern] = [];
            }

            $paths[$pattern][$method] = $this->buildOperation($pattern);
        }

        return $paths;
    }

    private function buildOperation(string $pattern): array
    {
        return [
            'parameters' => $this->extractParameters($pattern),
            'responses' => [
                '200' => [
                    'description' => 'Successful response',
                ],
            ],
        ];
    }

    private function extractParameters(string $pattern): array
    {
        $params = [];

        if (preg_match_all('/\{(\w+)\}/', $pattern, $matches)) {
            foreach ($matches[1] as $name) {
                $params[] = [
                    'name' => $name,
                    'in' => 'path',
                    'required' => true,
                    'schema' => [
                        'type' => 'string',
                    ],
                ];
            }
        }

        return $params;
    }
}
