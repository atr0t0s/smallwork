<?php
// src/Database/Adapters/QdrantAdapter.php
declare(strict_types=1);
namespace Smallwork\Database\Adapters;

use Smallwork\Database\VectorStoreInterface;

class QdrantAdapter implements VectorStoreInterface
{
    private string $baseUrl;
    private ?\Closure $httpClient;

    public function __construct(
        string $host = 'http://localhost',
        int $port = 6333,
        private ?string $apiKey = null,
        ?callable $httpClient = null,
    ) {
        $this->baseUrl = rtrim($host, '/') . ':' . $port;
        $this->httpClient = $httpClient ? $httpClient(...) : null;
    }

    public function createCollection(string $name, int $dimensions, string $distance = 'cosine'): void
    {
        $distanceMap = [
            'cosine' => 'Cosine',
            'euclidean' => 'Euclid',
            'dot' => 'Dot',
        ];

        $this->request('PUT', "/collections/$name", [
            'vectors' => [
                'size' => $dimensions,
                'distance' => $distanceMap[$distance] ?? 'Cosine',
            ],
        ]);
    }

    public function upsert(string $collection, array $vectors): void
    {
        $points = array_map(fn($v) => [
            'id' => $v['id'],
            'vector' => $v['vector'],
            'payload' => $v['payload'] ?? [],
        ], $vectors);

        $this->request('PUT', "/collections/$collection/points", [
            'points' => $points,
        ]);
    }

    public function search(string $collection, array $vector, int $limit = 10): array
    {
        $response = $this->request('POST', "/collections/$collection/points/search", [
            'vector' => $vector,
            'limit' => $limit,
            'with_payload' => true,
        ]);

        return array_map(fn($r) => [
            'id' => $r['id'],
            'score' => $r['score'],
            'payload' => $r['payload'] ?? [],
        ], $response['result'] ?? []);
    }

    public function delete(string $collection, array $ids): void
    {
        $this->request('POST', "/collections/$collection/points/delete", [
            'points' => $ids,
        ]);
    }

    private function request(string $method, string $path, ?array $body = null): array
    {
        $url = $this->baseUrl . $path;

        // Use mock client if provided (for testing)
        if ($this->httpClient) {
            $response = ($this->httpClient)($method, $url, $body);
            return $response['body'] ?? [];
        }

        // Real HTTP request via cURL
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => array_filter([
                'Content-Type: application/json',
                $this->apiKey ? "api-key: {$this->apiKey}" : null,
            ]),
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($statusCode >= 400) {
            throw new \RuntimeException("Qdrant request failed with status $statusCode: $response");
        }

        return json_decode($response, true) ?? [];
    }
}
