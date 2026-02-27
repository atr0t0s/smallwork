<?php
// src/Auth/JwtAuth.php
declare(strict_types=1);
namespace Smallwork\Auth;

class JwtAuth
{
    private const ALGORITHM = 'HS256';

    public function __construct(private string $secret) {}

    public function encode(array $payload, ?int $expiresIn = null): string
    {
        $header = ['alg' => self::ALGORITHM, 'typ' => 'JWT'];

        $payload['iat'] = time();
        if ($expiresIn !== null) {
            $payload['exp'] = $payload['iat'] + $expiresIn;
        }

        $segments = [
            $this->base64UrlEncode(json_encode($header)),
            $this->base64UrlEncode(json_encode($payload)),
        ];

        $signature = $this->sign(implode('.', $segments));
        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    public function decode(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new \RuntimeException('Invalid token format');
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        // Verify signature
        $expectedSignature = $this->sign("$headerB64.$payloadB64");
        $actualSignature = $this->base64UrlDecode($signatureB64);

        if (!hash_equals($expectedSignature, $actualSignature)) {
            throw new \RuntimeException('Invalid token signature');
        }

        $payload = json_decode($this->base64UrlDecode($payloadB64), true);
        if (!is_array($payload)) {
            throw new \RuntimeException('Invalid token payload');
        }

        // Check expiry
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            throw new \RuntimeException('Token has expired');
        }

        return $payload;
    }

    public function refresh(string $token, ?int $expiresIn = null): string
    {
        $payload = $this->decode($token);
        unset($payload['iat'], $payload['exp']);
        return $this->encode($payload, $expiresIn);
    }

    private function sign(string $data): string
    {
        return hash_hmac('sha256', $data, $this->secret, true);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        if ($decoded === false) {
            throw new \RuntimeException('Invalid base64 encoding');
        }
        return $decoded;
    }
}
