<?php
// src/AI/Middleware/ContentModeration.php
declare(strict_types=1);
namespace Smallwork\AI\Middleware;

use Smallwork\AI\Gateway;
use Smallwork\Core\Request;
use Smallwork\Core\Response;

class ContentModeration
{
    private array $fields;
    private string $prompt;

    public function __construct(
        private Gateway $gateway,
        array $fields = ['message', 'content', 'text'],
        string $prompt = 'Classify the following user content as either "safe" or "unsafe". Respond with only one word: safe or unsafe.',
        private ?string $provider = null,
    ) {
        $this->fields = $fields;
        $this->prompt = $prompt;
    }

    public function handle(Request $request, callable $next): Response
    {
        if ($request->method() === 'GET') {
            return $next($request);
        }

        $content = $this->extractContent($request);

        if ($content === null) {
            return $next($request);
        }

        if ($this->isUnsafe($content)) {
            return Response::json(
                ['error' => 'Content has been flagged as inappropriate and cannot be processed.'],
                422,
            );
        }

        return $next($request);
    }

    private function extractContent(Request $request): ?string
    {
        $json = $request->json();

        if (!is_array($json) || empty($json)) {
            return null;
        }

        $parts = [];
        foreach ($this->fields as $field) {
            if (isset($json[$field]) && is_string($json[$field]) && $json[$field] !== '') {
                $parts[] = $json[$field];
            }
        }

        return $parts !== [] ? implode("\n", $parts) : null;
    }

    private function isUnsafe(string $content): bool
    {
        $messages = [
            ['role' => 'system', 'content' => $this->prompt],
            ['role' => 'user', 'content' => $content],
        ];

        $result = $this->gateway->chat($messages, $this->provider);

        return str_contains(strtolower(trim($result['content'])), 'unsafe');
    }
}
