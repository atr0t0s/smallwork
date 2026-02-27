<?php
// src/AI/Middleware/IntentClassifier.php
declare(strict_types=1);
namespace Smallwork\AI\Middleware;

use Smallwork\AI\Gateway;
use Smallwork\Core\Request;
use Smallwork\Core\Response;

class IntentClassifier
{
    private array $categories;

    public function __construct(
        private Gateway $gateway,
        array $categories = ['question', 'command', 'feedback', 'other'],
        private ?string $provider = null,
    ) {
        $this->categories = $categories;
    }

    public function handle(Request $request, callable $next): Response
    {
        $text = $this->extractText($request);

        if ($text === null) {
            $request->setAttribute('intent', 'unknown');
            return $next($request);
        }

        $intent = $this->classify($text);
        $request->setAttribute('intent', $intent);

        return $next($request);
    }

    private function extractText(Request $request): ?string
    {
        if ($request->method() === 'GET') {
            return null;
        }

        $json = $request->json();
        if (!is_array($json)) {
            return null;
        }

        foreach (['message', 'content', 'text'] as $field) {
            if (isset($json[$field]) && is_string($json[$field]) && $json[$field] !== '') {
                return $json[$field];
            }
        }

        return null;
    }

    private function classify(string $text): string
    {
        $categoryList = implode(', ', $this->categories);

        $result = $this->gateway->chat([
            [
                'role' => 'system',
                'content' => "Classify the following text into exactly one of these categories: {$categoryList}. Respond with only the category name, nothing else.",
            ],
            [
                'role' => 'user',
                'content' => $text,
            ],
        ], $this->provider);

        $response = strtolower(trim($result['content'] ?? ''));

        if (in_array($response, $this->categories, true)) {
            return $response;
        }

        // If AI returned something not in our categories, default to last category (typically 'other')
        return $this->categories[array_key_last($this->categories)];
    }
}
