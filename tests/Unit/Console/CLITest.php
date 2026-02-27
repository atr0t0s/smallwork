<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use PHPUnit\Framework\TestCase;
use Smallwork\Console\CLI;
use Smallwork\Console\Command;
use Smallwork\Console\ServeCommand;
use Smallwork\Console\MigrateCommand;
use Smallwork\Console\MakeCommand;
use Smallwork\Database\Migrator;

class CLITest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/smallwork_cli_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    // --- CLI dispatch tests ---

    public function test_dispatches_to_registered_command(): void
    {
        $cli = new CLI();
        $command = new class extends Command {
            public bool $executed = false;
            public array $receivedArgs = [];

            public function getName(): string { return 'test'; }
            public function getDescription(): string { return 'A test command'; }
            public function execute(array $args): int
            {
                $this->executed = true;
                $this->receivedArgs = $args;
                return 0;
            }
        };

        $cli->register($command->getName(), $command);
        $exitCode = $cli->run(['smallwork', 'test', '--flag', 'value']);

        $this->assertTrue($command->executed);
        $this->assertSame(['--flag', 'value'], $command->receivedArgs);
        $this->assertSame(0, $exitCode);
    }

    public function test_shows_help_when_no_command_given(): void
    {
        $cli = new CLI();
        ob_start();
        $exitCode = $cli->run(['smallwork']);
        $output = ob_get_clean();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Usage:', $output);
        $this->assertStringContainsString('Available commands:', $output);
    }

    public function test_returns_error_for_unknown_command(): void
    {
        $cli = new CLI();
        ob_start();
        $exitCode = $cli->run(['smallwork', 'nonexistent']);
        $output = ob_get_clean();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Unknown command', $output);
    }

    public function test_list_command_shows_registered_commands(): void
    {
        $cli = new CLI();
        $command = new class extends Command {
            public function getName(): string { return 'greet'; }
            public function getDescription(): string { return 'Say hello'; }
            public function execute(array $args): int { return 0; }
        };
        $cli->register($command->getName(), $command);

        ob_start();
        $cli->run(['smallwork', 'list']);
        $output = ob_get_clean();

        $this->assertStringContainsString('greet', $output);
        $this->assertStringContainsString('Say hello', $output);
    }

    // --- ServeCommand tests ---

    public function test_serve_builds_default_command(): void
    {
        $command = new ServeCommand();
        $this->assertSame('serve', $command->getName());
        $this->assertStringContainsString('development server', $command->getDescription());

        $built = $command->buildCommand([]);
        $this->assertSame('php -S localhost:8080 -t public/', $built);
    }

    public function test_serve_accepts_custom_host_and_port(): void
    {
        $command = new ServeCommand();

        $built = $command->buildCommand(['--host', '0.0.0.0', '--port', '9000']);
        $this->assertSame('php -S 0.0.0.0:9000 -t public/', $built);
    }

    // --- MigrateCommand tests ---

    public function test_migrate_runs_migrations(): void
    {
        $migrator = $this->createMock(Migrator::class);
        $migrator->expects($this->once())
            ->method('migrate')
            ->willReturn(3);

        $command = new MigrateCommand($migrator);
        $this->assertSame('migrate', $command->getName());

        ob_start();
        $exitCode = $command->execute([]);
        $output = ob_get_clean();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('3', $output);
    }

    public function test_migrate_rollback(): void
    {
        $migrator = $this->createMock(Migrator::class);
        $migrator->expects($this->once())
            ->method('rollback')
            ->willReturn(2);

        $command = new MigrateCommand($migrator);

        ob_start();
        $exitCode = $command->execute(['--rollback']);
        $output = ob_get_clean();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('2', $output);
    }

    // --- MakeCommand tests ---

    public function test_make_controller_generates_file(): void
    {
        $command = new MakeCommand($this->tempDir);

        ob_start();
        $exitCode = $command->execute(['controller', 'User']);
        $output = ob_get_clean();

        $this->assertSame(0, $exitCode);
        $filePath = $this->tempDir . '/app/Controllers/UserController.php';
        $this->assertFileExists($filePath);

        $content = file_get_contents($filePath);
        $this->assertStringContainsString('namespace App\\Controllers', $content);
        $this->assertStringContainsString('class UserController', $content);
    }

    public function test_make_model_generates_file(): void
    {
        $command = new MakeCommand($this->tempDir);

        ob_start();
        $exitCode = $command->execute(['model', 'Post']);
        $output = ob_get_clean();

        $this->assertSame(0, $exitCode);
        $filePath = $this->tempDir . '/app/Models/Post.php';
        $this->assertFileExists($filePath);

        $content = file_get_contents($filePath);
        $this->assertStringContainsString('namespace App\\Models', $content);
        $this->assertStringContainsString('class Post', $content);
    }

    public function test_make_migration_generates_file(): void
    {
        $command = new MakeCommand($this->tempDir);

        ob_start();
        $exitCode = $command->execute(['migration', 'create_users_table']);
        $output = ob_get_clean();

        $this->assertSame(0, $exitCode);

        $files = glob($this->tempDir . '/database/migrations/*_create_users_table.php');
        $this->assertCount(1, $files);

        $content = file_get_contents($files[0]);
        $this->assertStringContainsString('up(', $content);
        $this->assertStringContainsString('down(', $content);
    }

    public function test_make_returns_error_for_unknown_type(): void
    {
        $command = new MakeCommand($this->tempDir);

        ob_start();
        $exitCode = $command->execute(['widget', 'Foo']);
        $output = ob_get_clean();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Unknown type', $output);
    }

    public function test_make_returns_error_when_no_args(): void
    {
        $command = new MakeCommand($this->tempDir);

        ob_start();
        $exitCode = $command->execute([]);
        $output = ob_get_clean();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Usage:', $output);
    }
}
