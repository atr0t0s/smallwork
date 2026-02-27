<?php
// src/Auth/ApiKeyAuth.php
declare(strict_types=1);
namespace Smallwork\Auth;

use Smallwork\Database\Adapters\PdoAdapter;

class ApiKeyAuth
{
    private const PREFIX = 'sw_';
    private const TABLE = 'api_keys';

    public function __construct(private PdoAdapter $db) {}

    public function createTable(): void
    {
        $this->db->execute('CREATE TABLE IF NOT EXISTS ' . self::TABLE . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            key_prefix TEXT NOT NULL,
            key_hash TEXT NOT NULL,
            permissions TEXT NOT NULL DEFAULT "[]",
            revoked INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL
        )');
    }

    /**
     * Generate a new API key. Returns ['id' => int, 'key' => string (plaintext, show once)].
     */
    public function generate(string $name, array $permissions = []): array
    {
        $rawKey = self::PREFIX . bin2hex(random_bytes(32));
        $prefix = substr($rawKey, 0, 10);
        $hash = password_hash($rawKey, PASSWORD_BCRYPT);

        $this->db->execute(
            'INSERT INTO ' . self::TABLE . ' (name, key_prefix, key_hash, permissions, created_at) VALUES (?, ?, ?, ?, ?)',
            [$name, $prefix, $hash, json_encode($permissions), date('Y-m-d H:i:s')]
        );

        return [
            'id' => $this->db->lastInsertId(),
            'key' => $rawKey,
        ];
    }

    /**
     * Verify an API key. Returns key record with permissions, or null if invalid.
     */
    public function verify(string $key): ?array
    {
        $prefix = substr($key, 0, 10);
        $rows = $this->db->fetchAll(
            'SELECT * FROM ' . self::TABLE . ' WHERE key_prefix = ? AND revoked = 0',
            [$prefix]
        );

        foreach ($rows as $row) {
            if (password_verify($key, $row['key_hash'])) {
                return [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'permissions' => json_decode($row['permissions'], true),
                    'created_at' => $row['created_at'],
                ];
            }
        }

        return null;
    }

    /**
     * Revoke a key by ID.
     */
    public function revoke(string|int $id): void
    {
        $this->db->execute(
            'UPDATE ' . self::TABLE . ' SET revoked = 1 WHERE id = ?',
            [(int) $id]
        );
    }

    /**
     * List all keys (without exposing hashes).
     */
    public function list(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT id, name, key_prefix, permissions, revoked, created_at FROM ' . self::TABLE . ' ORDER BY id'
        );

        return array_map(fn($row) => [
            'id' => $row['id'],
            'name' => $row['name'],
            'prefix' => $row['key_prefix'],
            'permissions' => json_decode($row['permissions'], true),
            'revoked' => (bool) $row['revoked'],
            'created_at' => $row['created_at'],
        ], $rows);
    }
}
