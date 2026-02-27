<?php
declare(strict_types=1);
namespace Smallwork\AI\Providers;

class GrokProvider implements ProviderInterface
{
    private ?\Closure $httpClient;

    public function __construct(
        private string $baseUrl,
        private string $apiKey,
        private string $defaultModel = 'grok-2',
        ?callable $httpClient = null,
    ) {
        $this->httpClient = $httpClient ? $httpClient(...) : null;
    }

    // Grok uses OpenAI-compatible format — same request/response structure
    public function chat(array $messages, array $options = []): array
    {
        $body = ['model' => $options['model'] ?? $this->defaultModel, 'messages' => $messages];
        foreach (['temperature', 'max_tokens', 'top_p'] as $k) {
            if (isset($options[$k])) {
                $body[$k] = $options[$k];
            }
        }
        $resp = $this->request('POST', '/chat/completions', $body);
        return [
            'content' => $resp['choices'][0]['message']['content'] ?? '',
            'usage' => $resp['usage'] ?? [],
            'model' => $resp['model'] ?? $body['model'],
        ];
    }

    public function embed(string|array $input, array $options = []): array
    {
        if (is_string($input)) {
            $input = [$input];
        }
        $body = ['model' => $options['model'] ?? 'grok-embed', 'input' => $input];
        $resp = $this->request('POST', '/embeddings', $body);
        return array_map(fn($d) => $d['embedding'], $resp['data'] ?? []);
    }

    public function streamChat(array $messages, callable $onChunk, array $options = []): array
    {
        $result = $this->chat($messages, $options);
        $onChunk($result['content']);
        return $result;
    }

    private function request(string $method, string $path, array $body): array
    {
        $url = rtrim($this->baseUrl, '/') . $path;
        $headers = ['Content-Type: application/json', "Authorization: Bearer {$this->apiKey}"];
        $jsonBody = json_encode($body);

        if ($this->httpClient) {
            $resp = ($this->httpClient)($method, $url, $headers, $jsonBody);
            return json_decode($resp['body'], true) ?? [];
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $jsonBody,
        ]);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status >= 400) {
            throw new \RuntimeException("Grok API error ($status): $response");
        }

        return json_decode($response, true) ?? [];
    }
}
