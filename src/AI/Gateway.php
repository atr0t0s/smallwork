<?php
// src/AI/Gateway.php
declare(strict_types=1);
namespace Smallwork\AI;

use Smallwork\AI\Providers\ProviderInterface;

class Gateway
{
    /** @var array<string, ProviderInterface> */
    private array $providers = [];

    public function __construct(private ?string $defaultProvider = null) {}

    public function register(string $name, ProviderInterface $provider): void
    {
        $this->providers[$name] = $provider;
    }

    public function chat(array $messages, ?string $provider = null, array $options = []): array
    {
        return $this->resolve($provider)->chat($messages, $options);
    }

    public function embed(string|array $input, ?string $provider = null, array $options = []): array
    {
        return $this->resolve($provider)->embed($input, $options);
    }

    public function streamChat(array $messages, callable $onChunk, ?string $provider = null, array $options = []): array
    {
        return $this->resolve($provider)->streamChat($messages, $onChunk, $options);
    }

    public function getProvider(string $name): ProviderInterface
    {
        return $this->resolve($name);
    }

    private function resolve(?string $name): ProviderInterface
    {
        $name = $name ?? $this->defaultProvider;
        if ($name === null) {
            throw new \RuntimeException('No AI provider specified and no default configured');
        }
        if (!isset($this->providers[$name])) {
            throw new \RuntimeException("AI provider '$name' is not registered");
        }
        return $this->providers[$name];
    }
}
