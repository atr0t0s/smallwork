<?php
// tests/Unit/AI/SemanticSearchTest.php
declare(strict_types=1);
namespace Tests\Unit\AI;

use PHPUnit\Framework\TestCase;
use Smallwork\AI\Gateway;
use Smallwork\AI\SemanticSearch;
use Smallwork\AI\Providers\ProviderInterface;
use Smallwork\Database\VectorStoreInterface;

class SemanticSearchTest extends TestCase
{
    private Gateway $gateway;
    private VectorStoreInterface $vectorStore;
    private SemanticSearch $search;

    protected function setUp(): void
    {
        $this->gateway = new Gateway('mock');
        $this->gateway->register('mock', $this->mockProvider([0.1, 0.2, 0.3]));

        $this->vectorStore = $this->createMock(VectorStoreInterface::class);
        $this->search = new SemanticSearch($this->gateway, $this->vectorStore, 'documents');
    }

    private function mockProvider(array $embedResponse): ProviderInterface
    {
        return new class($embedResponse) implements ProviderInterface {
            public function __construct(private array $embedResp) {}
            public function chat(array $messages, array $options = []): array {
                return ['content' => '', 'usage' => [], 'model' => 'mock'];
            }
            public function embed(string|array $input, array $options = []): array {
                if (is_string($input)) $input = [$input];
                return array_map(fn($t) => $this->embedResp, $input);
            }
            public function streamChat(array $messages, callable $onChunk, array $options = []): array {
                return ['content' => '', 'usage' => [], 'model' => 'mock'];
            }
        };
    }

    public function test_search_embeds_query_and_searches_vector_store(): void
    {
        $this->vectorStore
            ->expects($this->once())
            ->method('search')
            ->with('documents', [0.1, 0.2, 0.3], 10)
            ->willReturn([
                ['id' => 'doc1', 'score' => 0.95, 'payload' => ['text' => 'PHP is great', 'source' => 'blog']],
                ['id' => 'doc2', 'score' => 0.80, 'payload' => ['text' => 'PHP 8.2 features', 'source' => 'docs']],
            ]);

        $results = $this->search->search('What is PHP?');

        $this->assertCount(2, $results);
        $this->assertEquals('doc1', $results[0]['id']);
        $this->assertEquals(0.95, $results[0]['score']);
        $this->assertEquals('PHP is great', $results[0]['payload']['text']);
    }

    public function test_search_with_custom_limit(): void
    {
        $this->vectorStore
            ->expects($this->once())
            ->method('search')
            ->with('documents', [0.1, 0.2, 0.3], 3)
            ->willReturn([
                ['id' => 'doc1', 'score' => 0.95, 'payload' => ['text' => 'Result 1']],
                ['id' => 'doc2', 'score' => 0.90, 'payload' => ['text' => 'Result 2']],
                ['id' => 'doc3', 'score' => 0.85, 'payload' => ['text' => 'Result 3']],
            ]);

        $results = $this->search->search('query', limit: 3);

        $this->assertCount(3, $results);
    }

    public function test_search_returns_empty_when_no_matches(): void
    {
        $this->vectorStore
            ->method('search')
            ->willReturn([]);

        $results = $this->search->search('obscure query');

        $this->assertCount(0, $results);
    }

    public function test_format_rag_context(): void
    {
        $searchResults = [
            ['id' => 'doc1', 'score' => 0.95, 'payload' => ['text' => 'PHP is a server-side language.']],
            ['id' => 'doc2', 'score' => 0.80, 'payload' => ['text' => 'PHP 8.2 added readonly classes.']],
        ];

        $context = $this->search->formatRagContext('What is PHP?', $searchResults);

        $this->assertStringContainsString('What is PHP?', $context);
        $this->assertStringContainsString('PHP is a server-side language.', $context);
        $this->assertStringContainsString('PHP 8.2 added readonly classes.', $context);
    }

    public function test_format_rag_context_with_empty_results(): void
    {
        $context = $this->search->formatRagContext('What is PHP?', []);

        $this->assertStringContainsString('What is PHP?', $context);
        $this->assertStringContainsString('No relevant', $context);
    }

    public function test_index_stores_text_with_embedding(): void
    {
        $this->vectorStore
            ->expects($this->once())
            ->method('upsert')
            ->with('documents', $this->callback(function (array $vectors) {
                return count($vectors) === 1
                    && $vectors[0]['id'] === 'doc1'
                    && $vectors[0]['vector'] === [0.1, 0.2, 0.3]
                    && $vectors[0]['payload']['text'] === 'PHP is great';
            }));

        $this->search->index('doc1', 'PHP is great');
    }

    public function test_index_with_extra_payload(): void
    {
        $this->vectorStore
            ->expects($this->once())
            ->method('upsert')
            ->with('documents', $this->callback(function (array $vectors) {
                return $vectors[0]['payload']['text'] === 'PHP is great'
                    && $vectors[0]['payload']['source'] === 'blog'
                    && $vectors[0]['payload']['author'] === 'Jane';
            }));

        $this->search->index('doc1', 'PHP is great', ['source' => 'blog', 'author' => 'Jane']);
    }

    public function test_index_batch(): void
    {
        $this->vectorStore
            ->expects($this->once())
            ->method('upsert')
            ->with('documents', $this->callback(function (array $vectors) {
                return count($vectors) === 2
                    && $vectors[0]['id'] === 'doc1'
                    && $vectors[1]['id'] === 'doc2';
            }));

        $this->search->indexBatch([
            ['id' => 'doc1', 'text' => 'First document'],
            ['id' => 'doc2', 'text' => 'Second document'],
        ]);
    }

    public function test_uses_custom_provider_for_embedding(): void
    {
        $gateway = new Gateway();
        $gateway->register('embed-provider', $this->mockProvider([0.5, 0.6]));

        $vectorStore = $this->createMock(VectorStoreInterface::class);
        $vectorStore->method('search')->willReturn([]);

        $search = new SemanticSearch($gateway, $vectorStore, 'docs', provider: 'embed-provider');
        $results = $search->search('test query');

        $this->assertIsArray($results);
    }
}
