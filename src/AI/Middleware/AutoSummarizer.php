<?php
// src/AI/Middleware/AutoSummarizer.php
declare(strict_types=1);
namespace Smallwork\AI\Middleware;

use Smallwork\AI\Gateway;
use Smallwork\Core\Request;
use Smallwork\Core\Response;

class AutoSummarizer
{
    public function __construct(
        private Gateway $gateway,
        private int $threshold = 500,
        private ?string $provider = null,
    ) {}

    public function handle(Request $request, callable $next): Response
    {
        $text = $this->extractText($request);

        if ($text === null) {
            return $next($request);
        }

        if (mb_strlen($text) <= $this->threshold) {
            $request->setAttribute('summary', $text);
            return $next($request);
        }

        $result = $this->gateway->chat(
            [
                ['role' => 'system', 'content' => 'Summarize the following text concisely.'],
                ['role' => 'user', 'content' => $text],
            ],
            provider: $this->provider,
        );

        $request->setAttribute('summary', $result['content']);
        return $next($request);
    }

    private function extractText(Request $request): ?string
    {
        $json = $request->json();

        foreach (['message', 'content', 'text'] as $field) {
            if (isset($json[$field]) && is_string($json[$field])) {
                return $json[$field];
            }
        }

        return null;
    }
}
