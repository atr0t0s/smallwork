<?php
// src/Database/VectorStoreInterface.php
declare(strict_types=1);
namespace Smallwork\Database;

interface VectorStoreInterface
{
    /**
     * Create a collection/table for vectors.
     */
    public function createCollection(string $name, int $dimensions, string $distance = 'cosine'): void;

    /**
     * Insert or update vectors with optional payload.
     * @param array $vectors Array of ['id' => string, 'vector' => float[], 'payload' => array]
     */
    public function upsert(string $collection, array $vectors): void;

    /**
     * Search for similar vectors.
     * @return array Array of ['id' => string, 'score' => float, 'payload' => array]
     */
    public function search(string $collection, array $vector, int $limit = 10): array;

    /**
     * Delete vectors by ID.
     */
    public function delete(string $collection, array $ids): void;
}
