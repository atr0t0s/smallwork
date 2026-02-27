<?php
// src/Database/QueryBuilder.php
declare(strict_types=1);
namespace Smallwork\Database;

use Smallwork\Database\Adapters\PdoAdapter;

class QueryBuilder
{
    private array $columns = ['*'];
    private array $wheres = [];
    private array $bindings = [];
    private array $orderBys = [];
    private array $joins = [];
    private array $groupBys = [];
    private ?int $limitVal = null;
    private ?int $offsetVal = null;

    public function __construct(
        private PdoAdapter $db,
        private string $table,
    ) {}

    public function select(string ...$columns): self
    {
        $this->columns = $columns;
        return $this;
    }

    public function where(string $column, string $operator, mixed $value): self
    {
        $this->wheres[] = "$column $operator ?";
        $this->bindings[] = $value;
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orderBys[] = "$column $direction";
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limitVal = $limit;
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offsetVal = $offset;
        return $this;
    }

    public function join(string $table, string $col1, string $operator, string $col2, string $type = 'INNER'): self
    {
        $this->joins[] = "$type JOIN $table ON $col1 $operator $col2";
        return $this;
    }

    public function groupBy(string ...$columns): self
    {
        $this->groupBys = array_merge($this->groupBys, $columns);
        return $this;
    }

    public function get(): array
    {
        $sql = $this->buildSelect();
        return $this->db->fetchAll($sql, $this->bindings);
    }

    public function first(): ?array
    {
        $this->limitVal = 1;
        $sql = $this->buildSelect();
        return $this->db->fetchOne($sql, $this->bindings);
    }

    public function count(): int
    {
        $savedColumns = $this->columns;
        $this->columns = ['COUNT(*) as aggregate'];
        $sql = $this->buildSelect();
        $this->columns = $savedColumns;
        $row = $this->db->fetchOne($sql, $this->bindings);
        return (int) ($row['aggregate'] ?? 0);
    }

    public function insert(array $data): string
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO {$this->table} ($columns) VALUES ($placeholders)";
        $this->db->execute($sql, array_values($data));
        return $this->db->lastInsertId();
    }

    public function update(array $data): int
    {
        $sets = [];
        $params = [];
        foreach ($data as $col => $val) {
            $sets[] = "$col = ?";
            $params[] = $val;
        }
        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets);
        if ($this->wheres) {
            $sql .= ' WHERE ' . implode(' AND ', $this->wheres);
            $params = array_merge($params, $this->bindings);
        }
        return $this->db->execute($sql, $params);
    }

    public function delete(): int
    {
        $sql = "DELETE FROM {$this->table}";
        if ($this->wheres) {
            $sql .= ' WHERE ' . implode(' AND ', $this->wheres);
        }
        return $this->db->execute($sql, $this->bindings);
    }

    private function buildSelect(): string
    {
        $sql = 'SELECT ' . implode(', ', $this->columns) . " FROM {$this->table}";

        foreach ($this->joins as $join) {
            $sql .= " $join";
        }

        if ($this->wheres) {
            $sql .= ' WHERE ' . implode(' AND ', $this->wheres);
        }

        if ($this->groupBys) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groupBys);
        }

        if ($this->orderBys) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orderBys);
        }

        if ($this->limitVal !== null) {
            $sql .= " LIMIT {$this->limitVal}";
        }

        if ($this->offsetVal !== null) {
            $sql .= " OFFSET {$this->offsetVal}";
        }

        return $sql;
    }
}
