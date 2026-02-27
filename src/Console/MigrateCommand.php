<?php

declare(strict_types=1);

namespace Smallwork\Console;

use Smallwork\Database\Migrator;

class MigrateCommand extends Command
{
    public function __construct(private Migrator $migrator)
    {
    }

    public function getName(): string
    {
        return 'migrate';
    }

    public function getDescription(): string
    {
        return 'Run database migrations';
    }

    public function execute(array $args): int
    {
        if (in_array('--rollback', $args, true)) {
            $count = $this->migrator->rollback();
            echo "Rolled back {$count} migration(s).\n";
        } else {
            $count = $this->migrator->migrate();
            echo "Ran {$count} migration(s).\n";
        }

        return 0;
    }
}
