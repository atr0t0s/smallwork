<?php
// tests/Unit/Auth/JwtAuthTest.php
declare(strict_types=1);
namespace Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use Smallwork\Auth\JwtAuth;

class JwtAuthTest extends TestCase
{
    private JwtAuth $jwt;
    private string $secret = 'test-secret-key-minimum-32-chars!!';

    protected function setUp(): void
    {
        $this->jwt = new JwtAuth($this->secret);
    }

    public function test_encode_returns_three_part_token(): void
    {
        $token = $this->jwt->encode(['sub' => '123', 'name' => 'Alice']);
        $parts = explode('.', $token);
        $this->assertCount(3, $parts);
    }

    public function test_decode_returns_payload(): void
    {
        $token = $this->jwt->encode(['sub' => '123', 'name' => 'Alice']);
        $payload = $this->jwt->decode($token);
        $this->assertEquals('123', $payload['sub']);
        $this->assertEquals('Alice', $payload['name']);
    }

    public function test_decode_includes_iat_and_exp(): void
    {
        $token = $this->jwt->encode(['sub' => '123'], expiresIn: 3600);
        $payload = $this->jwt->decode($token);
        $this->assertArrayHasKey('iat', $payload);
        $this->assertArrayHasKey('exp', $payload);
        $this->assertEquals($payload['iat'] + 3600, $payload['exp']);
    }

    public function test_decode_rejects_tampered_token(): void
    {
        $token = $this->jwt->encode(['sub' => '123']);
        // Tamper with payload
        $parts = explode('.', $token);
        $parts[1] = base64_encode(json_encode(['sub' => 'hacked']));
        $tampered = implode('.', $parts);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid token signature');
        $this->jwt->decode($tampered);
    }

    public function test_decode_rejects_expired_token(): void
    {
        // Create a token that expired 10 seconds ago
        $jwt = new JwtAuth($this->secret);
        $token = $jwt->encode(['sub' => '123'], expiresIn: -10);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Token has expired');
        $jwt->decode($token);
    }

    public function test_decode_rejects_wrong_secret(): void
    {
        $token = $this->jwt->encode(['sub' => '123']);
        $otherJwt = new JwtAuth('different-secret-key-also-32-chars!');

        $this->expectException(\RuntimeException::class);
        $otherJwt->decode($token);
    }

    public function test_decode_rejects_malformed_token(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->jwt->decode('not.a.valid.token.at.all');
    }

    public function test_decode_rejects_invalid_base64(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->jwt->decode('x.y.z');
    }

    public function test_token_without_expiry(): void
    {
        $token = $this->jwt->encode(['sub' => '123']); // No expiresIn
        $payload = $this->jwt->decode($token);
        $this->assertEquals('123', $payload['sub']);
        $this->assertArrayHasKey('iat', $payload);
    }

    public function test_refresh_returns_new_token_with_same_claims(): void
    {
        $token = $this->jwt->encode(['sub' => '123', 'role' => 'admin'], expiresIn: 3600);
        $newToken = $this->jwt->refresh($token, expiresIn: 7200);

        $this->assertNotEquals($token, $newToken);
        $payload = $this->jwt->decode($newToken);
        $this->assertEquals('123', $payload['sub']);
        $this->assertEquals('admin', $payload['role']);
    }
}
