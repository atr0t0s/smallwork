<?php
// tests/Unit/Database/QdrantAdapterTest.php
declare(strict_types=1);
namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use Smallwork\Database\VectorStoreInterface;
use Smallwork\Database\Adapters\QdrantAdapter;

class QdrantAdapterTest extends TestCase
{
    private QdrantAdapter $qdrant;
    private array $httpLog;

    protected function setUp(): void
    {
        $this->httpLog = [];
        // Use a mock HTTP client that records requests
        $mockHttp = function (string $method, string $url, ?array $body = null): array {
            $this->httpLog[] = ['method' => $method, 'url' => $url, 'body' => $body];

            // Simulate Qdrant responses
            if (str_contains($url, '/collections/') && $method === 'PUT') {
                return ['status' => 200, 'body' => ['result' => true]];
            }
            if (str_contains($url, '/points') && $method === 'PUT') {
                return ['status' => 200, 'body' => ['result' => ['status' => 'completed']]];
            }
            if (str_contains($url, '/points/search') && $method === 'POST') {
                return ['status' => 200, 'body' => ['result' => [
                    ['id' => 'doc_1', 'score' => 0.95, 'payload' => ['text' => 'hello world']],
                    ['id' => 'doc_2', 'score' => 0.80, 'payload' => ['text' => 'foo bar']],
                ]]];
            }
            if (str_contains($url, '/points/delete') && $method === 'POST') {
                return ['status' => 200, 'body' => ['result' => true]];
            }
            return ['status' => 200, 'body' => []];
        };

        $this->qdrant = new QdrantAdapter('http://localhost', 6333, null, $mockHttp);
    }

    public function test_implements_vector_store_interface(): void
    {
        $this->assertInstanceOf(VectorStoreInterface::class, $this->qdrant);
    }

    public function test_create_collection(): void
    {
        $this->qdrant->createCollection('test_collection', 1536);

        $this->assertCount(1, $this->httpLog);
        $this->assertEquals('PUT', $this->httpLog[0]['method']);
        $this->assertStringContainsString('/collections/test_collection', $this->httpLog[0]['url']);
        $this->assertEquals(1536, $this->httpLog[0]['body']['vectors']['size']);
    }

    public function test_upsert(): void
    {
        $this->qdrant->upsert('test_collection', [
            ['id' => 'doc_1', 'vector' => array_fill(0, 3, 0.1), 'payload' => ['text' => 'hello']],
            ['id' => 'doc_2', 'vector' => array_fill(0, 3, 0.2), 'payload' => ['text' => 'world']],
        ]);

        $this->assertCount(1, $this->httpLog);
        $this->assertEquals('PUT', $this->httpLog[0]['method']);
        $this->assertStringContainsString('/collections/test_collection/points', $this->httpLog[0]['url']);
        $this->assertCount(2, $this->httpLog[0]['body']['points']);
    }

    public function test_search(): void
    {
        $results = $this->qdrant->search('test_collection', array_fill(0, 3, 0.1), 5);

        $this->assertCount(2, $results);
        $this->assertEquals('doc_1', $results[0]['id']);
        $this->assertEquals(0.95, $results[0]['score']);
        $this->assertEquals('hello world', $results[0]['payload']['text']);
    }

    public function test_delete(): void
    {
        $this->qdrant->delete('test_collection', ['doc_1', 'doc_2']);

        $this->assertCount(1, $this->httpLog);
        $this->assertEquals('POST', $this->httpLog[0]['method']);
        $this->assertStringContainsString('/points/delete', $this->httpLog[0]['url']);
    }
}
