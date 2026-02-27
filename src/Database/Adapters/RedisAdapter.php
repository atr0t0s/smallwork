<?php
// src/Database/Adapters/RedisAdapter.php
declare(strict_types=1);
namespace Smallwork\Database\Adapters;

class RedisAdapter
{
    private mixed $connection = null;
    private bool $inMemory = false;
    private array $store = [];
    private array $expiry = [];

    private function __construct(
        private string $host = '127.0.0.1',
        private int $port = 6379,
        private ?string $password = null,
        private int $database = 0,
    ) {}

    /**
     * Create a real Redis connection (socket-based).
     */
    public static function create(array $config): self
    {
        $adapter = new self(
            host: $config['host'] ?? '127.0.0.1',
            port: (int) ($config['port'] ?? 6379),
            password: $config['password'] ?? null,
            database: (int) ($config['database'] ?? 0),
        );
        return $adapter;
    }

    /**
     * Create an in-memory adapter for testing (no Redis server needed).
     */
    public static function createInMemory(): self
    {
        $adapter = new self();
        $adapter->inMemory = true;
        return $adapter;
    }

    public function get(string $key): ?string
    {
        if ($this->inMemory) {
            $this->checkExpiry($key);
            return $this->store[$key] ?? null;
        }
        return $this->command('GET', $key);
    }

    public function set(string $key, string $value, ?int $ttl = null): void
    {
        if ($this->inMemory) {
            $this->store[$key] = $value;
            if ($ttl !== null) {
                $this->expiry[$key] = time() + $ttl;
            }
            return;
        }
        if ($ttl !== null) {
            $this->command('SETEX', $key, (string) $ttl, $value);
        } else {
            $this->command('SET', $key, $value);
        }
    }

    public function delete(string $key): void
    {
        if ($this->inMemory) {
            unset($this->store[$key], $this->expiry[$key]);
            return;
        }
        $this->command('DEL', $key);
    }

    public function exists(string $key): bool
    {
        if ($this->inMemory) {
            $this->checkExpiry($key);
            return isset($this->store[$key]);
        }
        return (bool) $this->command('EXISTS', $key);
    }

    public function increment(string $key, int $by = 1): int
    {
        if ($this->inMemory) {
            $current = (int) ($this->store[$key] ?? 0);
            $new = $current + $by;
            $this->store[$key] = (string) $new;
            return $new;
        }
        return (int) $this->command('INCRBY', $key, (string) $by);
    }

    public function decrement(string $key, int $by = 1): int
    {
        if ($this->inMemory) {
            $current = (int) ($this->store[$key] ?? 0);
            $new = $current - $by;
            $this->store[$key] = (string) $new;
            return $new;
        }
        return (int) $this->command('DECRBY', $key, (string) $by);
    }

    public function flush(): void
    {
        if ($this->inMemory) {
            $this->store = [];
            $this->expiry = [];
            return;
        }
        $this->command('FLUSHDB');
    }

    private function checkExpiry(string $key): void
    {
        if (isset($this->expiry[$key]) && time() >= $this->expiry[$key]) {
            unset($this->store[$key], $this->expiry[$key]);
        }
    }

    private function connect(): void
    {
        if ($this->connection !== null) return;
        $this->connection = @fsockopen($this->host, $this->port, $errno, $errstr, 5);
        if (!$this->connection) {
            throw new \RuntimeException("Could not connect to Redis at {$this->host}:{$this->port}: $errstr");
        }
        if ($this->password) {
            $this->command('AUTH', $this->password);
        }
        if ($this->database > 0) {
            $this->command('SELECT', (string) $this->database);
        }
    }

    private function command(string ...$args): ?string
    {
        $this->connect();
        $cmd = '*' . count($args) . "\r\n";
        foreach ($args as $arg) {
            $cmd .= '$' . strlen($arg) . "\r\n" . $arg . "\r\n";
        }
        fwrite($this->connection, $cmd);
        return $this->readResponse();
    }

    private function readResponse(): ?string
    {
        $line = fgets($this->connection);
        if ($line === false) return null;
        $type = $line[0];
        $data = trim(substr($line, 1));

        return match ($type) {
            '+' => $data,                    // Simple string
            '-' => throw new \RuntimeException("Redis error: $data"),
            ':' => $data,                    // Integer
            '$' => $this->readBulk((int) $data),  // Bulk string
            '*' => $this->readArray((int) $data),  // Array (simplified)
            default => null,
        };
    }

    private function readBulk(int $length): ?string
    {
        if ($length === -1) return null;
        $data = '';
        $remaining = $length + 2; // +2 for \r\n
        while ($remaining > 0) {
            $chunk = fread($this->connection, $remaining);
            if ($chunk === false) break;
            $data .= $chunk;
            $remaining -= strlen($chunk);
        }
        return substr($data, 0, $length);
    }

    private function readArray(int $count): ?string
    {
        if ($count === -1) return null;
        // Simplified: just read all elements and return last
        $result = null;
        for ($i = 0; $i < $count; $i++) {
            $result = $this->readResponse();
        }
        return $result;
    }

    public function __destruct()
    {
        if ($this->connection && is_resource($this->connection)) {
            fclose($this->connection);
        }
    }
}
