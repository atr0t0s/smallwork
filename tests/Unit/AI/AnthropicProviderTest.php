<?php
declare(strict_types=1);
namespace Tests\Unit\AI;

use PHPUnit\Framework\TestCase;
use Smallwork\AI\Providers\AnthropicProvider;
use Smallwork\AI\Providers\ProviderInterface;

class AnthropicProviderTest extends TestCase
{
    private array $httpLog;
    private AnthropicProvider $provider;

    protected function setUp(): void
    {
        $this->httpLog = [];
        $mockHttp = function(string $method, string $url, array $headers, ?string $body): array {
            $this->httpLog[] = ['method'=>$method, 'url'=>$url, 'headers'=>$headers, 'body'=>$body ? json_decode($body,true) : null];
            return ['status'=>200, 'body'=>json_encode([
                'content'=>[['type'=>'text','text'=>'Hello from Claude!']],
                'usage'=>['input_tokens'=>10,'output_tokens'=>5],
                'model'=>'claude-sonnet-4-6',
            ])];
        };
        $this->provider = new AnthropicProvider('https://api.anthropic.com/v1', 'test-key', 'claude-sonnet-4-6', $mockHttp);
    }

    public function test_implements_interface(): void { $this->assertInstanceOf(ProviderInterface::class, $this->provider); }

    public function test_chat(): void {
        $result = $this->provider->chat([
            ['role'=>'system','content'=>'You are helpful.'],
            ['role'=>'user','content'=>'Hi'],
        ]);
        $this->assertEquals('Hello from Claude!', $result['content']);
        // Anthropic sends system separately
        $this->assertArrayHasKey('system', $this->httpLog[0]['body']);
        $this->assertEquals('You are helpful.', $this->httpLog[0]['body']['system']);
    }

    public function test_sends_anthropic_headers(): void {
        $this->provider->chat([['role'=>'user','content'=>'Hi']]);
        $headers = $this->httpLog[0]['headers'];
        $this->assertContains('x-api-key: test-key', $headers);
        $hasVersion = false;
        foreach ($headers as $h) { if (str_starts_with($h, 'anthropic-version:')) $hasVersion = true; }
        $this->assertTrue($hasVersion);
    }

    public function test_normalizes_usage(): void {
        $result = $this->provider->chat([['role'=>'user','content'=>'Hi']]);
        $this->assertArrayHasKey('usage', $result);
        $this->assertEquals(10, $result['usage']['prompt_tokens']);
        $this->assertEquals(5, $result['usage']['completion_tokens']);
        $this->assertEquals(15, $result['usage']['total_tokens']);
    }

    public function test_embed_throws_not_supported(): void {
        // Anthropic doesn't have an embeddings API
        $this->expectException(\RuntimeException::class);
        $this->provider->embed('Hello');
    }

    public function test_filters_system_from_messages(): void {
        $this->provider->chat([
            ['role'=>'system','content'=>'Be nice.'],
            ['role'=>'user','content'=>'Hi'],
            ['role'=>'assistant','content'=>'Hello'],
            ['role'=>'user','content'=>'Bye'],
        ]);
        $messages = $this->httpLog[0]['body']['messages'];
        // System should not be in messages array
        foreach ($messages as $m) { $this->assertNotEquals('system', $m['role']); }
        $this->assertCount(3, $messages);
    }
}
