<?php
// src/Database/Schema.php
declare(strict_types=1);
namespace Smallwork\Database;

use Smallwork\Database\Adapters\PdoAdapter;

class Schema
{
    private array $columns = [];
    private bool $building = false;

    public function __construct(private PdoAdapter $db) {}

    public function create(string $table, callable $callback): void
    {
        $this->columns = [];
        $this->building = true;
        $callback($this);
        $this->building = false;

        $columnDefs = implode(', ', $this->columns);
        $sql = "CREATE TABLE $table ($columnDefs)";
        $this->db->execute($sql);
        $this->columns = [];
    }

    public function drop(string $table): void
    {
        $this->db->execute("DROP TABLE IF EXISTS $table");
    }

    public function id(string $name = 'id'): self
    {
        $this->columns[] = "$name INTEGER PRIMARY KEY AUTOINCREMENT";
        return $this;
    }

    public function string(string $name, int $length = 255): self
    {
        $this->columns[] = "$name VARCHAR($length) NOT NULL DEFAULT ''";
        return $this;
    }

    public function text(string $name): self
    {
        $this->columns[] = "$name TEXT NOT NULL DEFAULT ''";
        return $this;
    }

    public function integer(string $name): self
    {
        $this->columns[] = "$name INTEGER NOT NULL DEFAULT 0";
        return $this;
    }

    public function float(string $name): self
    {
        $this->columns[] = "$name REAL NOT NULL DEFAULT 0";
        return $this;
    }

    public function boolean(string $name): self
    {
        $this->columns[] = "$name INTEGER NOT NULL DEFAULT 0";
        return $this;
    }

    public function timestamps(): self
    {
        $this->columns[] = "created_at TEXT DEFAULT NULL";
        $this->columns[] = "updated_at TEXT DEFAULT NULL";
        return $this;
    }

    public function nullable(): self
    {
        if ($this->columns) {
            $last = array_pop($this->columns);
            $last = str_replace(' NOT NULL', '', $last);
            $last = preg_replace("/ DEFAULT '[^']*'/", ' DEFAULT NULL', $last);
            $last = preg_replace('/ DEFAULT \d+/', ' DEFAULT NULL', $last);
            $this->columns[] = $last;
        }
        return $this;
    }
}
