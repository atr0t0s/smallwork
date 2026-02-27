<?php
// src/AI/SemanticSearch.php
declare(strict_types=1);
namespace Smallwork\AI;

use Smallwork\Database\VectorStoreInterface;

class SemanticSearch
{
    public function __construct(
        private Gateway $gateway,
        private VectorStoreInterface $vectorStore,
        private string $collection,
        private ?string $provider = null,
    ) {}

    /**
     * Search for documents similar to the query string.
     *
     * @return array Array of ['id' => string, 'score' => float, 'payload' => array]
     */
    public function search(string $query, int $limit = 10): array
    {
        $vectors = $this->gateway->embed($query, $this->provider);
        $queryVector = $vectors[0];

        return $this->vectorStore->search($this->collection, $queryVector, $limit);
    }

    /**
     * Format search results as RAG context for injection into chat messages.
     */
    public function formatRagContext(string $query, array $searchResults): string
    {
        if (empty($searchResults)) {
            return "Query: {$query}\n\nNo relevant documents found.";
        }

        $context = "Query: {$query}\n\nRelevant context:\n\n";

        foreach ($searchResults as $i => $result) {
            $num = $i + 1;
            $text = $result['payload']['text'] ?? '';
            $score = round($result['score'], 2);
            $context .= "[{$num}] (score: {$score}) {$text}\n\n";
        }

        return rtrim($context);
    }

    /**
     * Index a single document: embed the text and store it in the vector store.
     */
    public function index(string $id, string $text, array $payload = []): void
    {
        $vectors = $this->gateway->embed($text, $this->provider);

        $payload['text'] = $text;

        $this->vectorStore->upsert($this->collection, [
            ['id' => $id, 'vector' => $vectors[0], 'payload' => $payload],
        ]);
    }

    /**
     * Index a batch of documents.
     *
     * @param array $documents Array of ['id' => string, 'text' => string, 'payload' => array]
     */
    public function indexBatch(array $documents): void
    {
        $texts = array_map(fn(array $doc) => $doc['text'], $documents);
        $embeddings = $this->gateway->embed($texts, $this->provider);

        $vectors = [];
        foreach ($documents as $i => $doc) {
            $payload = $doc['payload'] ?? [];
            $payload['text'] = $doc['text'];
            $vectors[] = [
                'id' => $doc['id'],
                'vector' => $embeddings[$i],
                'payload' => $payload,
            ];
        }

        $this->vectorStore->upsert($this->collection, $vectors);
    }
}
