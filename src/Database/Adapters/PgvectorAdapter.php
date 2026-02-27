<?php
// src/Database/Adapters/PgvectorAdapter.php
declare(strict_types=1);
namespace Smallwork\Database\Adapters;

use Smallwork\Database\VectorStoreInterface;

class PgvectorAdapter implements VectorStoreInterface
{
    private string $defaultDistance = 'cosine';

    public function __construct(private mixed $db) {}

    public function createCollection(string $name, int $dimensions, string $distance = 'cosine'): void
    {
        $this->defaultDistance = $distance;

        $this->db->execute('CREATE EXTENSION IF NOT EXISTS vector');
        $this->db->execute("CREATE TABLE IF NOT EXISTS $name (
            id TEXT PRIMARY KEY,
            embedding vector($dimensions),
            payload JSONB DEFAULT '{}'
        )");
    }

    public function upsert(string $collection, array $vectors): void
    {
        foreach ($vectors as $v) {
            $vectorStr = '[' . implode(',', $v['vector']) . ']';
            $payload = json_encode($v['payload'] ?? []);
            $this->db->execute(
                "INSERT INTO $collection (id, embedding, payload) VALUES (?, ?::vector, ?::jsonb)
                 ON CONFLICT (id) DO UPDATE SET embedding = EXCLUDED.embedding, payload = EXCLUDED.payload",
                [$v['id'], $vectorStr, $payload]
            );
        }
    }

    public function search(string $collection, array $vector, int $limit = 10): array
    {
        $vectorStr = '[' . implode(',', $vector) . ']';
        $operator = $this->getDistanceOperator();

        $rows = $this->db->fetchAll(
            "SELECT id, embedding $operator ?::vector AS distance, payload
             FROM $collection
             ORDER BY embedding $operator ?::vector ASC
             LIMIT ?",
            [$vectorStr, $vectorStr, $limit]
        );

        return array_map(fn($row) => [
            'id' => $row['id'],
            'score' => 1.0 - (float) $row['distance'],
            'payload' => json_decode($row['payload'] ?? '{}', true),
        ], $rows);
    }

    public function delete(string $collection, array $ids): void
    {
        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $this->db->execute(
            "DELETE FROM $collection WHERE id IN ($placeholders)",
            $ids
        );
    }

    private function getDistanceOperator(): string
    {
        return match ($this->defaultDistance) {
            'cosine' => '<=>',
            'euclidean' => '<->',
            'dot' => '<#>',
            default => '<=>',
        };
    }
}
