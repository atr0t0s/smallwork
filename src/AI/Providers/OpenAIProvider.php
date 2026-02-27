<?php
declare(strict_types=1);
namespace Smallwork\AI\Providers;

class OpenAIProvider implements ProviderInterface
{
    private ?\Closure $httpClient;

    public function __construct(
        private string $baseUrl,
        private string $apiKey,
        private string $defaultModel = 'gpt-4o',
        ?callable $httpClient = null,
    ) {
        $this->httpClient = $httpClient ? $httpClient(...) : null;
    }

    public function chat(array $messages, array $options = []): array
    {
        $body = ['model' => $options['model'] ?? $this->defaultModel, 'messages' => $messages];
        foreach (['temperature', 'max_tokens', 'top_p', 'frequency_penalty', 'presence_penalty'] as $k) {
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
        $body = ['model' => $options['model'] ?? 'text-embedding-3-small', 'input' => $input];
        $resp = $this->request('POST', '/embeddings', $body);
        return array_map(fn($d) => $d['embedding'], $resp['data'] ?? []);
    }

    public function streamChat(array $messages, callable $onChunk, array $options = []): array
    {
        $body = ['model' => $options['model'] ?? $this->defaultModel, 'messages' => $messages, 'stream' => true];
        // For real streaming we'd use chunked cURL; mock returns full response
        $resp = $this->request('POST', '/chat/completions', $body);
        $content = $resp['choices'][0]['message']['content'] ?? '';
        $onChunk($content);
        return ['content' => $content, 'usage' => $resp['usage'] ?? [], 'model' => $resp['model'] ?? ''];
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
            throw new \RuntimeException("OpenAI API error ($status): $response");
        }

        return json_decode($response, true) ?? [];
    }
}
