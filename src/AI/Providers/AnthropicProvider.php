<?php
declare(strict_types=1);
namespace Smallwork\AI\Providers;

class AnthropicProvider implements ProviderInterface
{
    private ?\Closure $httpClient;

    public function __construct(
        private string $baseUrl,
        private string $apiKey,
        private string $defaultModel = 'claude-sonnet-4-6',
        ?callable $httpClient = null,
    ) {
        $this->httpClient = $httpClient ? $httpClient(...) : null;
    }

    public function chat(array $messages, array $options = []): array
    {
        $system = null;
        $filtered = [];

        foreach ($messages as $m) {
            if ($m['role'] === 'system') {
                $system = $m['content'];
            } else {
                $filtered[] = $m;
            }
        }

        $body = [
            'model' => $options['model'] ?? $this->defaultModel,
            'messages' => $filtered,
            'max_tokens' => $options['max_tokens'] ?? 4096,
        ];

        if ($system !== null) {
            $body['system'] = $system;
        }

        if (isset($options['temperature'])) {
            $body['temperature'] = $options['temperature'];
        }

        $resp = $this->request('POST', '/messages', $body);

        $content = $resp['content'][0]['text'] ?? '';
        $usage = $resp['usage'] ?? [];

        return [
            'content' => $content,
            'usage' => [
                'prompt_tokens' => $usage['input_tokens'] ?? 0,
                'completion_tokens' => $usage['output_tokens'] ?? 0,
                'total_tokens' => ($usage['input_tokens'] ?? 0) + ($usage['output_tokens'] ?? 0),
            ],
            'model' => $resp['model'] ?? $body['model'],
        ];
    }

    public function embed(string|array $input, array $options = []): array
    {
        throw new \RuntimeException('Anthropic does not support embeddings. Use OpenAI or another provider.');
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
        $headers = [
            'Content-Type: application/json',
            "x-api-key: {$this->apiKey}",
            'anthropic-version: 2023-06-01',
        ];
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
            throw new \RuntimeException("Anthropic API error ($status): $response");
        }

        return json_decode($response, true) ?? [];
    }
}
