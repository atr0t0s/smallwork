<?php
// tests/Unit/Auth/ApiKeyAuthTest.php
declare(strict_types=1);
namespace Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use Smallwork\Auth\ApiKeyAuth;
use Smallwork\Database\Connection;
use Smallwork\Database\Adapters\PdoAdapter;

class ApiKeyAuthTest extends TestCase
{
    private ApiKeyAuth $auth;
    private PdoAdapter $db;

    protected function setUp(): void
    {
        $this->db = Connection::create(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->auth = new ApiKeyAuth($this->db);
        $this->auth->createTable();
    }

    public function test_generate_returns_plaintext_key(): void
    {
        $result = $this->auth->generate('Test Key', ['chat:read', 'chat:write']);
        $this->assertArrayHasKey('key', $result);
        $this->assertArrayHasKey('id', $result);
        $this->assertNotEmpty($result['key']);
        // Key should be a reasonable length
        $this->assertGreaterThan(30, strlen($result['key']));
    }

    public function test_verify_valid_key(): void
    {
        $result = $this->auth->generate('My App', ['chat:read']);
        $record = $this->auth->verify($result['key']);

        $this->assertNotNull($record);
        $this->assertEquals('My App', $record['name']);
        $this->assertContains('chat:read', $record['permissions']);
    }

    public function test_verify_invalid_key(): void
    {
        $record = $this->auth->verify('sk_invalid_key_that_does_not_exist');
        $this->assertNull($record);
    }

    public function test_verify_rejects_tampered_key(): void
    {
        $result = $this->auth->generate('App', []);
        $tampered = $result['key'] . 'tampered';
        $record = $this->auth->verify($tampered);
        $this->assertNull($record);
    }

    public function test_revoke_key(): void
    {
        $result = $this->auth->generate('Temp Key', []);
        $this->auth->revoke($result['id']);
        $record = $this->auth->verify($result['key']);
        $this->assertNull($record);
    }

    public function test_list_keys(): void
    {
        $this->auth->generate('Key 1', ['read']);
        $this->auth->generate('Key 2', ['read', 'write']);

        $keys = $this->auth->list();
        $this->assertCount(2, $keys);
        // Should not expose the hash
        $this->assertArrayNotHasKey('key_hash', $keys[0]);
        $this->assertArrayHasKey('name', $keys[0]);
    }

    public function test_permissions_stored_correctly(): void
    {
        $result = $this->auth->generate('Multi-perm', ['chat:read', 'embed:write', 'admin']);
        $record = $this->auth->verify($result['key']);

        $this->assertEquals(['chat:read', 'embed:write', 'admin'], $record['permissions']);
    }

    public function test_generate_creates_unique_keys(): void
    {
        $key1 = $this->auth->generate('K1', [])['key'];
        $key2 = $this->auth->generate('K2', [])['key'];
        $this->assertNotEquals($key1, $key2);
    }
}
