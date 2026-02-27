<?php
declare(strict_types=1);
namespace Tests\Unit\AI;

use PHPUnit\Framework\TestCase;
use Smallwork\AI\Providers\GrokProvider;
use Smallwork\AI\Providers\ProviderInterface;

class GrokProviderTest extends TestCase
{
    private array $httpLog;
    private GrokProvider $provider;

    protected function setUp(): void
    {
        $this->httpLog = [];
        $mockHttp = function(string $method, string $url, array $headers, ?string $body): array {
            $this->httpLog[] = ['method'=>$method, 'url'=>$url, 'headers'=>$headers, 'body'=>$body ? json_decode($body,true) : null];
            if (str_contains($url, '/chat/completions')) {
                return ['status'=>200, 'body'=>json_encode([
                    'choices'=>[['message'=>['content'=>'Hello from Grok!']]],
                    'usage'=>['prompt_tokens'=>8,'completion_tokens'=>4,'total_tokens'=>12],
                    'model'=>'grok-2'
                ])];
            }
            if (str_contains($url, '/embeddings')) {
                return ['status'=>200, 'body'=>json_encode(['data'=>[['embedding'=>[0.4,0.5]]]])];
            }
            return ['status'=>200, 'body'=>'{}'];
        };
        $this->provider = new GrokProvider('https://api.x.ai/v1', 'xai-test-key', 'grok-2', $mockHttp);
    }

    public function test_implements_interface(): void { $this->assertInstanceOf(ProviderInterface::class, $this->provider); }

    public function test_chat(): void {
        $result = $this->provider->chat([['role'=>'user','content'=>'Hi']]);
        $this->assertEquals('Hello from Grok!', $result['content']);
        $this->assertStringContainsString('api.x.ai', $this->httpLog[0]['url']);
    }

    public function test_sends_bearer_auth(): void {
        $this->provider->chat([['role'=>'user','content'=>'Hi']]);
        $this->assertContains('Authorization: Bearer xai-test-key', $this->httpLog[0]['headers']);
    }

    public function test_uses_grok_model(): void {
        $this->provider->chat([['role'=>'user','content'=>'Hi']]);
        $this->assertEquals('grok-2', $this->httpLog[0]['body']['model']);
    }

    public function test_embed(): void {
        $result = $this->provider->embed('Hello');
        $this->assertEquals([[0.4,0.5]], $result);
    }
}
