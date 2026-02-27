<?php

declare(strict_types=1);

namespace Smallwork\Console;

class CLI
{
    /** @var array<string, Command> */
    private array $commands = [];

    public function register(string $name, Command $command): void
    {
        $this->commands[$name] = $command;
    }

    public function run(array $argv): int
    {
        $commandName = $argv[1] ?? null;
        $args = array_slice($argv, 2);

        if ($commandName === null || $commandName === 'help') {
            $this->showHelp();
            return 0;
        }

        if ($commandName === 'list') {
            $this->showHelp();
            return 0;
        }

        if (!isset($this->commands[$commandName])) {
            echo "Unknown command: {$commandName}\n\n";
            $this->showHelp();
            return 1;
        }

        return $this->commands[$commandName]->execute($args);
    }

    private function showHelp(): void
    {
        echo "Usage: smallwork <command> [arguments]\n\n";
        echo "Available commands:\n";

        foreach ($this->commands as $name => $command) {
            echo sprintf("  %-20s %s\n", $name, $command->getDescription());
        }

        if (empty($this->commands)) {
            echo "  (no commands registered)\n";
        }

        echo "\n";
    }
}
