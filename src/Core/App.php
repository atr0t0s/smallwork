<?php

declare(strict_types=1);

namespace Smallwork\Core;

class App
{
    private function __construct(private string $basePath) {}

    public static function create(string $basePath): self
    {
        return new self($basePath);
    }

    public function run(): void
    {
        // Will be implemented in Task 7
    }

    public function runCli(array $argv): void
    {
        echo "Smallwork CLI - coming soon\n";
    }
}
