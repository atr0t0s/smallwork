<?php
// src/Database/Migrator.php
declare(strict_types=1);
namespace Smallwork\Database;

use Smallwork\Database\Adapters\PdoAdapter;

class Migrator
{
    private Schema $schema;

    public function __construct(
        private PdoAdapter $db,
        private string $migrationsPath,
    ) {
        $this->schema = new Schema($db);
        $this->ensureMigrationsTable();
    }

    public function migrate(): int
    {
        $files = $this->getMigrationFiles();
        $ran = $this->getRanMigrations();
        $count = 0;

        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $ran)) {
                continue;
            }

            $migration = require $file;
            $migration->up($this->schema);
            $this->db->execute(
                'INSERT INTO _migrations (migration, ran_at) VALUES (?, ?)',
                [$name, date('Y-m-d H:i:s')]
            );
            $count++;
        }

        return $count;
    }

    public function rollback(): int
    {
        $ran = $this->getRanMigrations();
        if (empty($ran)) {
            return 0;
        }

        $count = 0;
        foreach (array_reverse($ran) as $name) {
            $file = $this->migrationsPath . '/' . $name;
            if (!file_exists($file)) {
                continue;
            }

            $migration = require $file;
            $migration->down($this->schema);
            $this->db->execute('DELETE FROM _migrations WHERE migration = ?', [$name]);
            $count++;
        }

        return $count;
    }

    private function ensureMigrationsTable(): void
    {
        $this->db->execute('CREATE TABLE IF NOT EXISTS _migrations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            migration TEXT NOT NULL,
            ran_at TEXT NOT NULL
        )');
    }

    private function getMigrationFiles(): array
    {
        $files = glob($this->migrationsPath . '/*.php');
        sort($files);
        return $files;
    }

    private function getRanMigrations(): array
    {
        $rows = $this->db->fetchAll('SELECT migration FROM _migrations ORDER BY id');
        return array_column($rows, 'migration');
    }
}
