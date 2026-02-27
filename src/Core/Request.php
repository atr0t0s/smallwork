<?php

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

    public function setAttribute(string $key, mixed $value): void
    {
        $this->post['_attrs'][$key] = $value;
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->post['_attrs'][$key] ?? $default;
    }
}
