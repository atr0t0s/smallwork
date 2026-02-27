<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Smallwork\Core\Container;

class ContainerTest extends TestCase
{
    public function test_bind_and_resolve(): void
    {
        $container = new Container();
        $container->bind('greeting', fn() => 'hello');
        $this->assertEquals('hello', $container->resolve('greeting'));
    }

    public function test_singleton(): void
    {
        $container = new Container();
        $count = 0;
        $container->singleton('counter', function () use (&$count) {
            $count++;
            return new \stdClass();
        });
        $a = $container->resolve('counter');
        $b = $container->resolve('counter');
        $this->assertSame($a, $b);
        $this->assertEquals(1, $count);
    }

    public function test_bind_instance(): void
    {
        $container = new Container();
        $obj = new \stdClass();
        $obj->name = 'test';
        $container->instance('myobj', $obj);
        $this->assertSame($obj, $container->resolve('myobj'));
    }

    public function test_has(): void
    {
        $container = new Container();
        $container->bind('exists', fn() => true);
        $this->assertTrue($container->has('exists'));
        $this->assertFalse($container->has('missing'));
    }

    public function test_throws_on_missing_binding(): void
    {
        $container = new Container();
        $this->expectException(\RuntimeException::class);
        $container->resolve('nonexistent');
    }

    public function test_autowire_constructor(): void
    {
        $container = new Container();
        $container->bind(DummyDep::class, fn() => new DummyDep('injected'));
        $resolved = $container->make(DummyService::class);
        $this->assertInstanceOf(DummyService::class, $resolved);
        $this->assertEquals('injected', $resolved->dep->value);
    }
}

class DummyDep
{
    public function __construct(public string $value) {}
}

class DummyService
{
    public function __construct(public DummyDep $dep) {}
}
