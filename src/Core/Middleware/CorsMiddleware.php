<?php
// src/Core/Middleware/CorsMiddleware.php
declare(strict_types=1);
namespace Smallwork\Core\Middleware;

use Smallwork\Core\Request;
use Smallwork\Core\Response;

class CorsMiddleware
{
    public function __construct(
        private array $allowedOrigins = ['*'],
        private array $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        private array $allowedHeaders = ['Content-Type', 'Authorization', 'X-API-Key'],
        private int $maxAge = 86400,
    ) {}

    public function handle(Request $request, callable $next): Response
    {
        $origin = $request->header('Origin');
        $allowed = $this->isOriginAllowed($origin);

        if ($request->method() === 'OPTIONS') {
            $response = Response::empty(204);
        } else {
            $response = $next($request);
        }

        if ($allowed) {
            $allowOrigin = in_array('*', $this->allowedOrigins) ? '*' : $origin;
            $response = $response
                ->withHeader('Access-Control-Allow-Origin', $allowOrigin)
                ->withHeader('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods))
                ->withHeader('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders))
                ->withHeader('Access-Control-Max-Age', (string) $this->maxAge);
        }
        return $response;
    }

    private function isOriginAllowed(?string $origin): bool
    {
        if (in_array('*', $this->allowedOrigins)) return true;
        if ($origin === null) return false;
        return in_array($origin, $this->allowedOrigins);
    }
}
