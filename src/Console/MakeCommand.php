<?php

declare(strict_types=1);

namespace Smallwork\Console;

class MakeCommand extends Command
{
    public function __construct(private string $basePath)
    {
    }

    public function getName(): string
    {
        return 'make';
    }

    public function getDescription(): string
    {
        return 'Generate boilerplate files (make:controller, make:model, make:migration)';
    }

    public function execute(array $args): int
    {
        if (count($args) < 2) {
            echo "Usage: smallwork make <type> <name>\n";
            echo "Types: controller, model, migration\n";
            return 1;
        }

        $type = $args[0];
        $name = $args[1];

        return match ($type) {
            'controller' => $this->makeController($name),
            'model' => $this->makeModel($name),
            'migration' => $this->makeMigration($name),
            default => $this->unknownType($type),
        };
    }

    private function makeController(string $name): int
    {
        $dir = $this->basePath . '/app/Controllers';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $className = $name . 'Controller';
        $filePath = $dir . '/' . $className . '.php';

        $content = <<<PHP
        <?php

        declare(strict_types=1);

        namespace App\\Controllers;

        use Smallwork\\Core\\Request;
        use Smallwork\\Core\\Response;

        class {$className}
        {
            public function index(Request \$request): Response
            {
                return new Response(200, ['{$name} index']);
            }
        }
        PHP;

        file_put_contents($filePath, $content);
        echo "Created controller: {$filePath}\n";
        return 0;
    }

    private function makeModel(string $name): int
    {
        $dir = $this->basePath . '/app/Models';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filePath = $dir . '/' . $name . '.php';

        $content = <<<PHP
        <?php

        declare(strict_types=1);

        namespace App\\Models;

        class {$name}
        {
            protected string \$table = '{$this->toTableName($name)}';
        }
        PHP;

        file_put_contents($filePath, $content);
        echo "Created model: {$filePath}\n";
        return 0;
    }

    private function makeMigration(string $name): int
    {
        $dir = $this->basePath . '/database/migrations';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $timestamp = date('Y_m_d_His');
        $filePath = $dir . '/' . $timestamp . '_' . $name . '.php';

        $content = <<<'PHP'
        <?php

        declare(strict_types=1);

        use Smallwork\Database\Schema;

        return new class {
            public function up(Schema $schema): void
            {
                // Define your migration here
            }

            public function down(Schema $schema): void
            {
                // Reverse the migration here
            }
        };
        PHP;

        file_put_contents($filePath, $content);
        echo "Created migration: {$filePath}\n";
        return 0;
    }

    private function unknownType(string $type): int
    {
        echo "Unknown type: {$type}\n";
        echo "Available types: controller, model, migration\n";
        return 1;
    }

    private function toTableName(string $className): string
    {
        $snake = strtolower(preg_replace('/[A-Z]/', '_$0', lcfirst($className)));
        return $snake . 's';
    }
}
