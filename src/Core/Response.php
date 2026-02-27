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
        return new self($html, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
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

    public function withCookie(
        string $name,
        string $value,
        int $maxAge = 0,
        string $path = '/',
        bool $httpOnly = true,
        bool $secure = false,
    ): self {
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

    public function streamCallback(): ?\Closure
    {
        return $this->streamCallback;
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }

        foreach ($this->cookies as $cookie) {
            setcookie($cookie['name'], $cookie['value'], [
                'expires' => $cookie['maxAge'] > 0 ? time() + $cookie['maxAge'] : 0,
                'path' => $cookie['path'],
                'httponly' => $cookie['httpOnly'],
                'secure' => $cookie['secure'],
            ]);
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
