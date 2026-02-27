<?php
// tests/Unit/Database/ConnectionTest.php
declare(strict_types=1);
namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use Smallwork\Database\Connection;
use Smallwork\Database\Adapters\PdoAdapter;

class ConnectionTest extends TestCase
{
    public function test_creates_sqlite_connection(): void
    {
        $adapter = Connection::create([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        $this->assertInstanceOf(PdoAdapter::class, $adapter);
    }

    public function test_sqlite_can_execute_queries(): void
    {
        $db = Connection::create(['driver' => 'sqlite', 'database' => ':memory:']);
        $db->execute('CREATE TABLE test (id INTEGER PRIMARY KEY, name TEXT)');
        $db->execute('INSERT INTO test (name) VALUES (?)', ['hello']);
        $rows = $db->fetchAll('SELECT * FROM test');
        $this->assertCount(1, $rows);
        $this->assertEquals('hello', $rows[0]['name']);
    }

    public function test_fetch_one(): void
    {
        $db = Connection::create(['driver' => 'sqlite', 'database' => ':memory:']);
        $db->execute('CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT)');
        $db->execute('INSERT INTO users (email) VALUES (?)', ['a@b.com']);
        $db->execute('INSERT INTO users (email) VALUES (?)', ['c@d.com']);
        $row = $db->fetchOne('SELECT * FROM users WHERE email = ?', ['a@b.com']);
        $this->assertEquals('a@b.com', $row['email']);
    }

    public function test_fetch_one_returns_null_when_not_found(): void
    {
        $db = Connection::create(['driver' => 'sqlite', 'database' => ':memory:']);
        $db->execute('CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT)');
        $row = $db->fetchOne('SELECT * FROM users WHERE email = ?', ['nope@nope.com']);
        $this->assertNull($row);
    }

    public function test_last_insert_id(): void
    {
        $db = Connection::create(['driver' => 'sqlite', 'database' => ':memory:']);
        $db->execute('CREATE TABLE items (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
        $db->execute('INSERT INTO items (name) VALUES (?)', ['first']);
        $this->assertEquals('1', $db->lastInsertId());
        $db->execute('INSERT INTO items (name) VALUES (?)', ['second']);
        $this->assertEquals('2', $db->lastInsertId());
    }

    public function test_transaction_commit(): void
    {
        $db = Connection::create(['driver' => 'sqlite', 'database' => ':memory:']);
        $db->execute('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT)');

        $db->beginTransaction();
        $db->execute('INSERT INTO items (name) VALUES (?)', ['in-tx']);
        $db->commit();

        $rows = $db->fetchAll('SELECT * FROM items');
        $this->assertCount(1, $rows);
    }

    public function test_transaction_rollback(): void
    {
        $db = Connection::create(['driver' => 'sqlite', 'database' => ':memory:']);
        $db->execute('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT)');

        $db->beginTransaction();
        $db->execute('INSERT INTO items (name) VALUES (?)', ['will-rollback']);
        $db->rollback();

        $rows = $db->fetchAll('SELECT * FROM items');
        $this->assertCount(0, $rows);
    }

    public function test_throws_on_unsupported_driver(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Connection::create(['driver' => 'oracle', 'database' => 'test']);
    }

    public function test_mysql_dsn_format(): void
    {
        // We can't connect to MySQL in tests, but we can verify DSN building
        $dsn = Connection::buildDsn([
            'driver' => 'mysql',
            'host' => 'localhost',
            'port' => 3306,
            'database' => 'mydb',
            'charset' => 'utf8mb4',
        ]);
        $this->assertEquals('mysql:host=localhost;port=3306;dbname=mydb;charset=utf8mb4', $dsn);
    }

    public function test_pgsql_dsn_format(): void
    {
        $dsn = Connection::buildDsn([
            'driver' => 'pgsql',
            'host' => 'localhost',
            'port' => 5432,
            'database' => 'mydb',
        ]);
        $this->assertEquals('pgsql:host=localhost;port=5432;dbname=mydb', $dsn);
    }
}
