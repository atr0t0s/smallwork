<?php

declare(strict_types=1);

namespace Smallwork\Console;

class ServeCommand extends Command
{
    public function getName(): string
    {
        return 'serve';
    }

    public function getDescription(): string
    {
        return 'Start development server';
    }

    public function execute(array $args): int
    {
        $command = $this->buildCommand($args);
        echo "Starting server: {$command}\n";
        passthru($command, $exitCode);
        return $exitCode;
    }

    public function buildCommand(array $args): string
    {
        $host = 'localhost';
        $port = '8080';

        for ($i = 0; $i < count($args); $i++) {
            if ($args[$i] === '--host' && isset($args[$i + 1])) {
                $host = $args[++$i];
            } elseif ($args[$i] === '--port' && isset($args[$i + 1])) {
                $port = $args[++$i];
            }
        }

        return "php -S {$host}:{$port} -t public/";
    }
}
