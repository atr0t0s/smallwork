<?php
// tests/Unit/Database/PgvectorAdapterTest.php
declare(strict_types=1);
namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use Smallwork\Database\VectorStoreInterface;
use Smallwork\Database\Adapters\PgvectorAdapter;

class PgvectorAdapterTest extends TestCase
{
    private PgvectorAdapter $adapter;
    private array $sqlLog;

    protected function setUp(): void
    {
        $this->sqlLog = [];
        // Mock PdoAdapter that records calls
        $mockDb = new class($this->sqlLog) {
            private array $log;
            public function __construct(array &$log) { $this->log = &$log; }
            public function execute(string $sql, array $params = []): int {
                $this->log[] = ['type' => 'execute', 'sql' => $sql, 'params' => $params];
                return 1;
            }
            public function fetchAll(string $sql, array $params = []): array {
                $this->log[] = ['type' => 'fetchAll', 'sql' => $sql, 'params' => $params];
                return [
                    ['id' => 'doc_1', 'distance' => 0.05, 'payload' => '{"text":"hello"}'],
                    ['id' => 'doc_2', 'distance' => 0.20, 'payload' => '{"text":"world"}'],
                ];
            }
            public function fetchOne(string $sql, array $params = []): ?array {
                $this->log[] = ['type' => 'fetchOne', 'sql' => $sql, 'params' => $params];
                return null;
            }
        };

        $this->adapter = new PgvectorAdapter($mockDb);
    }

    public function test_implements_vector_store_interface(): void
    {
        $this->assertInstanceOf(VectorStoreInterface::class, $this->adapter);
    }

    public function test_create_collection(): void
    {
        $this->adapter->createCollection('documents', 1536);

        // Should execute: CREATE EXTENSION IF NOT EXISTS vector, then CREATE TABLE
        $this->assertGreaterThanOrEqual(2, count($this->sqlLog));
        $createTable = $this->sqlLog[1]['sql'] ?? $this->sqlLog[0]['sql'];
        $this->assertStringContainsString('documents', $createTable);
        $this->assertStringContainsString('vector(1536)', $createTable);
    }

    public function test_upsert(): void
    {
        $this->adapter->upsert('documents', [
            ['id' => 'doc_1', 'vector' => [0.1, 0.2, 0.3], 'payload' => ['text' => 'hello']],
        ]);

        $this->assertCount(1, $this->sqlLog);
        $this->assertStringContainsString('INSERT INTO documents', $this->sqlLog[0]['sql']);
        $this->assertStringContainsString('ON CONFLICT', $this->sqlLog[0]['sql']);
    }

    public function test_search(): void
    {
        $results = $this->adapter->search('documents', [0.1, 0.2, 0.3], 5);

        $this->assertCount(1, $this->sqlLog);
        $this->assertStringContainsString('ORDER BY', $this->sqlLog[0]['sql']);
        $this->assertStringContainsString('LIMIT', $this->sqlLog[0]['sql']);

        // Results should be parsed with score = 1 - distance for cosine
        $this->assertCount(2, $results);
        $this->assertEquals('doc_1', $results[0]['id']);
        $this->assertIsFloat($results[0]['score']);
        $this->assertEquals('hello', $results[0]['payload']['text']);
    }

    public function test_delete(): void
    {
        $this->adapter->delete('documents', ['doc_1', 'doc_2']);

        $this->assertCount(1, $this->sqlLog);
        $this->assertStringContainsString('DELETE FROM documents', $this->sqlLog[0]['sql']);
    }

    public function test_create_collection_with_different_distances(): void
    {
        $this->adapter->createCollection('test_cosine', 3, 'cosine');
        $cosineSQL = end($this->sqlLog)['sql'];

        $this->sqlLog = [];
        $this->adapter->createCollection('test_l2', 3, 'euclidean');
        $l2SQL = end($this->sqlLog)['sql'];

        // Both should create valid tables (distance metric affects search, not schema in pgvector)
        $this->assertStringContainsString('vector(3)', $cosineSQL);
        $this->assertStringContainsString('vector(3)', $l2SQL);
    }
}
