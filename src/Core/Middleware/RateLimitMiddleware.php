<?php
// src/Core/Middleware/RateLimitMiddleware.php
declare(strict_types=1);
namespace Smallwork\Core\Middleware;

use Smallwork\Core\Request;
use Smallwork\Core\Response;

class RateLimitMiddleware
{
    private static array $store = [];

    public function __construct(
        private int $maxRequests = 60,
        private int $windowSeconds = 60,
    ) {}

    public function handle(Request $request, callable $next): Response
    {
        $key = $this->resolveKey($request);
        $now = microtime(true);

        if (!isset(self::$store[$key]) || $now >= self::$store[$key]['resetAt']) {
            self::$store[$key] = ['count' => 0, 'resetAt' => $now + $this->windowSeconds];
        }

        self::$store[$key]['count']++;
        $remaining = max(0, $this->maxRequests - self::$store[$key]['count']);

        if (self::$store[$key]['count'] > $this->maxRequests) {
            return Response::json([
                'error' => 'Too Many Requests',
                'retry_after' => (int) ceil(self::$store[$key]['resetAt'] - $now),
            ], 429)
                ->withHeader('X-RateLimit-Limit', (string) $this->maxRequests)
                ->withHeader('X-RateLimit-Remaining', '0')
                ->withHeader('Retry-After', (string) (int) ceil(self::$store[$key]['resetAt'] - $now));
        }

        return $next($request)
            ->withHeader('X-RateLimit-Limit', (string) $this->maxRequests)
            ->withHeader('X-RateLimit-Remaining', (string) $remaining);
    }

    private function resolveKey(Request $request): string
    {
        return $request->header('X-Forwarded-For') ?? $request->header('X-Real-Ip') ?? 'unknown';
    }

    public static function reset(): void
    {
        self::$store = [];
    }
}
