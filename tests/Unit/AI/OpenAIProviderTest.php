<?php
declare(strict_types=1);
namespace Tests\Unit\AI;

use PHPUnit\Framework\TestCase;
use Smallwork\AI\Providers\OpenAIProvider;
use Smallwork\AI\Providers\ProviderInterface;

class OpenAIProviderTest extends TestCase
{
    private array $httpLog;
    private OpenAIProvider $provider;

    protected function setUp(): void
    {
        $this->httpLog = [];
        $mockHttp = function(string $method, string $url, array $headers, ?string $body): array {
            $this->httpLog[] = ['method'=>$method, 'url'=>$url, 'headers'=>$headers, 'body'=>$body ? json_decode($body,true) : null];
            if (str_contains($url, '/chat/completions')) {
                return ['status'=>200, 'body'=>json_encode([
                    'choices'=>[['message'=>['content'=>'Hello!']]],
                    'usage'=>['prompt_tokens'=>10,'completion_tokens'=>5,'total_tokens'=>15],
                    'model'=>'gpt-4o'
                ])];
            }
            if (str_contains($url, '/embeddings')) {
                return ['status'=>200, 'body'=>json_encode([
                    'data'=>[['embedding'=>[0.1,0.2,0.3]]]
                ])];
            }
            return ['status'=>200, 'body'=>'{}'];
        };
        $this->provider = new OpenAIProvider('https://api.openai.com/v1', 'test-key', 'gpt-4o', $mockHttp);
    }

    public function test_implements_interface(): void { $this->assertInstanceOf(ProviderInterface::class, $this->provider); }

    public function test_chat(): void {
        $result = $this->provider->chat([['role'=>'user','content'=>'Hi']]);
        $this->assertEquals('Hello!', $result['content']);
        $this->assertEquals(15, $result['usage']['total_tokens']);
        $this->assertStringContainsString('/chat/completions', $this->httpLog[0]['url']);
        $this->assertEquals('user', $this->httpLog[0]['body']['messages'][0]['role']);
    }

    public function test_chat_sends_auth_header(): void {
        $this->provider->chat([['role'=>'user','content'=>'Hi']]);
        $this->assertContains('Authorization: Bearer test-key', $this->httpLog[0]['headers']);
    }

    public function test_chat_passes_model_and_options(): void {
        $this->provider->chat([['role'=>'user','content'=>'Hi']], ['model'=>'gpt-4o-mini','temperature'=>0.5]);
        $this->assertEquals('gpt-4o-mini', $this->httpLog[0]['body']['model']);
        $this->assertEquals(0.5, $this->httpLog[0]['body']['temperature']);
    }

    public function test_embed(): void {
        $result = $this->provider->embed('Hello');
        $this->assertCount(1, $result);
        $this->assertEquals([0.1,0.2,0.3], $result[0]);
    }

    public function test_embed_batch(): void {
        $result = $this->provider->embed(['text1','text2']);
        $this->assertStringContainsString('/embeddings', $this->httpLog[0]['url']);
    }
}
