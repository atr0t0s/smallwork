<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Smallwork\Core\Request;

class RequestTest extends TestCase
{
    public function test_creates_from_globals(): void
    {
        $request = Request::create('GET', '/api/v1/users', query: ['page' => '1']);
        $this->assertEquals('GET', $request->method());
        $this->assertEquals('/api/v1/users', $request->path());
        $this->assertEquals('1', $request->query('page'));
    }

    public function test_parses_json_body(): void
    {
        $request = Request::create('POST', '/api/chat', body: '{"message":"hello"}', headers: ['Content-Type' => 'application/json']);
        $this->assertEquals('hello', $request->json('message'));
        $this->assertEquals(['message' => 'hello'], $request->json());
    }

    public function test_input_merges_query_and_post(): void
    {
        $request = Request::create('POST', '/search', query: ['page' => '1'], post: ['q' => 'test']);
        $this->assertEquals('1', $request->input('page'));
        $this->assertEquals('test', $request->input('q'));
        $this->assertEquals('default', $request->input('missing', 'default'));
    }

    public function test_headers(): void
    {
        $request = Request::create('GET', '/', headers: ['Authorization' => 'Bearer token123', 'Accept' => 'application/json']);
        $this->assertEquals('Bearer token123', $request->header('Authorization'));
        $this->assertEquals('application/json', $request->header('Accept'));
        $this->assertNull($request->header('X-Missing'));
    }

    public function test_route_parameters(): void
    {
        $request = Request::create('GET', '/users/42');
        $request->setRouteParams(['id' => '42']);
        $this->assertEquals('42', $request->param('id'));
        $this->assertNull($request->param('missing'));
    }

    public function test_method_detection(): void
    {
        $request = Request::create('POST', '/');
        $this->assertTrue($request->isPost());
        $this->assertFalse($request->isGet());
        $get = Request::create('GET', '/');
        $this->assertTrue($get->isGet());
    }

    public function test_captures_from_server_globals(): void
    {
        $request = Request::capture([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/api/test?foo=bar',
            'HTTP_AUTHORIZATION' => 'Bearer abc',
            'CONTENT_TYPE' => 'application/json',
        ], ['foo' => 'bar'], ['name' => 'test'], '{"data":1}');
        $this->assertEquals('POST', $request->method());
        $this->assertEquals('/api/test', $request->path());
        $this->assertEquals('bar', $request->query('foo'));
        $this->assertEquals('test', $request->input('name'));
        $this->assertEquals(1, $request->json('data'));
        $this->assertEquals('Bearer abc', $request->header('Authorization'));
    }
}
