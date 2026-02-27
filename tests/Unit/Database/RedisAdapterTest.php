<?php
// tests/Unit/Database/RedisAdapterTest.php
declare(strict_types=1);
namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use Smallwork\Database\Adapters\RedisAdapter;

class RedisAdapterTest extends TestCase
{
    private RedisAdapter $redis;

    protected function setUp(): void
    {
        // Use in-memory mode for testing (no real Redis needed)
        $this->redis = RedisAdapter::createInMemory();
    }

    public function test_set_and_get(): void
    {
        $this->redis->set('key1', 'value1');
        $this->assertEquals('value1', $this->redis->get('key1'));
    }

    public function test_get_returns_null_for_missing_key(): void
    {
        $this->assertNull($this->redis->get('nonexistent'));
    }

    public function test_delete(): void
    {
        $this->redis->set('key1', 'value1');
        $this->redis->delete('key1');
        $this->assertNull($this->redis->get('key1'));
    }

    public function test_exists(): void
    {
        $this->redis->set('key1', 'value1');
        $this->assertTrue($this->redis->exists('key1'));
        $this->assertFalse($this->redis->exists('missing'));
    }

    public function test_increment(): void
    {
        $this->redis->set('counter', '5');
        $result = $this->redis->increment('counter');
        $this->assertEquals(6, $result);
        $this->assertEquals('6', $this->redis->get('counter'));
    }

    public function test_increment_nonexistent_starts_at_one(): void
    {
        $result = $this->redis->increment('new_counter');
        $this->assertEquals(1, $result);
    }

    public function test_decrement(): void
    {
        $this->redis->set('counter', '5');
        $result = $this->redis->decrement('counter');
        $this->assertEquals(4, $result);
    }

    public function test_set_with_ttl(): void
    {
        $this->redis->set('expiring', 'value', ttl: 1);
        $this->assertEquals('value', $this->redis->get('expiring'));
        // We can't easily test TTL expiry in unit tests without sleeping,
        // but we verify the method accepts the parameter
    }

    public function test_set_json_and_get_json(): void
    {
        $data = ['user' => 'Alice', 'age' => 30];
        $this->redis->set('user:1', json_encode($data));
        $retrieved = json_decode($this->redis->get('user:1'), true);
        $this->assertEquals($data, $retrieved);
    }

    public function test_flush(): void
    {
        $this->redis->set('a', '1');
        $this->redis->set('b', '2');
        $this->redis->flush();
        $this->assertNull($this->redis->get('a'));
        $this->assertNull($this->redis->get('b'));
    }
}
