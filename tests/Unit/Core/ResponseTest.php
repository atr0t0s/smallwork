<?php
// tests/Unit/Core/ResponseTest.php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Smallwork\Core\Response;

class ResponseTest extends TestCase
{
    public function test_json_response(): void
    {
        $response = Response::json(['message' => 'hello'], 200);
        $this->assertEquals(200, $response->status());
        $this->assertEquals('application/json', $response->header('Content-Type'));
        $this->assertEquals('{"message":"hello"}', $response->body());
    }

    public function test_json_with_custom_status(): void
    {
        $response = Response::json(['error' => 'not found'], 404);
        $this->assertEquals(404, $response->status());
    }

    public function test_html_response(): void
    {
        $response = Response::html('<h1>Hello</h1>', 200);
        $this->assertEquals('text/html; charset=UTF-8', $response->header('Content-Type'));
        $this->assertEquals('<h1>Hello</h1>', $response->body());
    }

    public function test_empty_response(): void
    {
        $response = Response::empty(204);
        $this->assertEquals(204, $response->status());
        $this->assertEquals('', $response->body());
    }

    public function test_redirect(): void
    {
        $response = Response::redirect('/login', 302);
        $this->assertEquals(302, $response->status());
        $this->assertEquals('/login', $response->header('Location'));
    }

    public function test_with_header(): void
    {
        $response = Response::json(['ok' => true])
            ->withHeader('X-Custom', 'value');
        $this->assertEquals('value', $response->header('X-Custom'));
    }

    public function test_with_cookie(): void
    {
        $response = Response::json(['ok' => true])
            ->withCookie('token', 'abc123', 3600);
        $cookies = $response->cookies();
        $this->assertCount(1, $cookies);
        $this->assertEquals('token', $cookies[0]['name']);
        $this->assertEquals('abc123', $cookies[0]['value']);
    }

    public function test_stream_creates_callable_response(): void
    {
        $response = Response::stream(function (callable $write) {
            $write('chunk1');
            $write('chunk2');
        });
        $this->assertTrue($response->isStream());
    }
}
