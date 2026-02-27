<?php
// src/Database/Connection.php
declare(strict_types=1);
namespace Smallwork\Database;

use Smallwork\Database\Adapters\PdoAdapter;

class Connection
{
    public static function create(array $config): PdoAdapter
    {
        $driver = $config['driver'] ?? '';
        $dsn = self::buildDsn($config);

        $username = $config['username'] ?? null;
        $password = $config['password'] ?? null;
        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $pdo = new \PDO($dsn, $username, $password, $options);
        return new PdoAdapter($pdo);
    }

    public static function buildDsn(array $config): string
    {
        $driver = $config['driver'] ?? '';
        return match ($driver) {
            'sqlite' => 'sqlite:' . ($config['database'] ?? ':memory:'),
            'mysql' => sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $config['host'] ?? 'localhost',
                $config['port'] ?? 3306,
                $config['database'] ?? '',
                $config['charset'] ?? 'utf8mb4',
            ),
            'pgsql' => sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                $config['host'] ?? 'localhost',
                $config['port'] ?? 5432,
                $config['database'] ?? '',
            ),
            default => throw new \InvalidArgumentException("Unsupported database driver: '$driver'"),
        };
    }
}
