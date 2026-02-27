<?php
// src/Core/Middleware/AuthMiddleware.php
declare(strict_types=1);
namespace Smallwork\Core\Middleware;

use Smallwork\Core\Request;
use Smallwork\Core\Response;
use Smallwork\Auth\JwtAuth;

class AuthMiddleware
{
    private function __construct(
        private string $strategy,
        private mixed $provider,
    ) {}

    public static function jwt(JwtAuth $jwt): self
    {
        return new self('jwt', $jwt);
    }

    public static function apiKey(mixed $apiKeyAuth): self
    {
        return new self('api_key', $apiKeyAuth);
    }

    public function handle(Request $request, callable $next): Response
    {
        $token = $this->extractToken($request);

        if ($token === null) {
            return Response::json(['error' => 'Authentication required'], 401);
        }

        return match ($this->strategy) {
            'jwt' => $this->handleJwt($request, $next, $token),
            'api_key' => $this->handleApiKey($request, $next, $token),
            default => Response::json(['error' => 'Unknown auth strategy'], 500),
        };
    }

    private function handleJwt(Request $request, callable $next, string $token): Response
    {
        try {
            $payload = $this->provider->decode($token);
            $request->setAttribute('user', $payload);
            return $next($request);
        } catch (\RuntimeException) {
            return Response::json(['error' => 'Invalid or expired token'], 401);
        }
    }

    private function handleApiKey(Request $request, callable $next, string $key): Response
    {
        $record = $this->provider->verify($key);
        if ($record === null) {
            return Response::json(['error' => 'Invalid API key'], 401);
        }
        $request->setAttribute('user', $record);
        return $next($request);
    }

    private function extractToken(Request $request): ?string
    {
        // Try Authorization: Bearer <token>
        $auth = $request->header('Authorization');
        if ($auth !== null && str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }

        // Try X-Api-Key header
        $apiKey = $request->header('X-Api-Key');
        if ($apiKey !== null) {
            return $apiKey;
        }

        return null;
    }
}
