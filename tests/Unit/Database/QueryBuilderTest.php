<?php
// tests/Unit/Database/QueryBuilderTest.php
declare(strict_types=1);
namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use Smallwork\Database\Connection;
use Smallwork\Database\QueryBuilder;
use Smallwork\Database\Adapters\PdoAdapter;

class QueryBuilderTest extends TestCase
{
    private PdoAdapter $db;

    protected function setUp(): void
    {
        $this->db = Connection::create(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->db->execute('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, email TEXT, age INTEGER, active INTEGER DEFAULT 1)');
        $this->db->execute('CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, title TEXT, body TEXT)');
    }

    private function qb(string $table): QueryBuilder
    {
        return new QueryBuilder($this->db, $table);
    }

    public function test_insert_and_select_all(): void
    {
        $this->qb('users')->insert(['name' => 'Alice', 'email' => 'alice@test.com', 'age' => 30]);
        $this->qb('users')->insert(['name' => 'Bob', 'email' => 'bob@test.com', 'age' => 25]);

        $rows = $this->qb('users')->get();
        $this->assertCount(2, $rows);
    }

    public function test_select_specific_columns(): void
    {
        $this->qb('users')->insert(['name' => 'Alice', 'email' => 'alice@test.com', 'age' => 30]);
        $rows = $this->qb('users')->select('name', 'email')->get();
        $this->assertArrayHasKey('name', $rows[0]);
        $this->assertArrayHasKey('email', $rows[0]);
        $this->assertArrayNotHasKey('age', $rows[0]);
    }

    public function test_where_clause(): void
    {
        $this->qb('users')->insert(['name' => 'Alice', 'email' => 'a@t.com', 'age' => 30]);
        $this->qb('users')->insert(['name' => 'Bob', 'email' => 'b@t.com', 'age' => 25]);

        $rows = $this->qb('users')->where('age', '>', 26)->get();
        $this->assertCount(1, $rows);
        $this->assertEquals('Alice', $rows[0]['name']);
    }

    public function test_where_equals_shorthand(): void
    {
        $this->qb('users')->insert(['name' => 'Alice', 'email' => 'a@t.com', 'age' => 30]);
        $rows = $this->qb('users')->where('name', '=', 'Alice')->get();
        $this->assertCount(1, $rows);
    }

    public function test_multiple_where(): void
    {
        $this->qb('users')->insert(['name' => 'Alice', 'email' => 'a@t.com', 'age' => 30]);
        $this->qb('users')->insert(['name' => 'Bob', 'email' => 'b@t.com', 'age' => 30]);
        $this->qb('users')->insert(['name' => 'Carol', 'email' => 'c@t.com', 'age' => 25]);

        $rows = $this->qb('users')->where('age', '=', 30)->where('name', '=', 'Alice')->get();
        $this->assertCount(1, $rows);
    }

    public function test_order_by(): void
    {
        $this->qb('users')->insert(['name' => 'Bob', 'email' => 'b@t.com', 'age' => 25]);
        $this->qb('users')->insert(['name' => 'Alice', 'email' => 'a@t.com', 'age' => 30]);

        $rows = $this->qb('users')->orderBy('name', 'ASC')->get();
        $this->assertEquals('Alice', $rows[0]['name']);
        $this->assertEquals('Bob', $rows[1]['name']);
    }

    public function test_limit_and_offset(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->qb('users')->insert(['name' => "User$i", 'email' => "u$i@t.com", 'age' => 20 + $i]);
        }

        $rows = $this->qb('users')->orderBy('id', 'ASC')->limit(2)->offset(1)->get();
        $this->assertCount(2, $rows);
        $this->assertEquals('User2', $rows[0]['name']);
    }

    public function test_first(): void
    {
        $this->qb('users')->insert(['name' => 'Alice', 'email' => 'a@t.com', 'age' => 30]);
        $this->qb('users')->insert(['name' => 'Bob', 'email' => 'b@t.com', 'age' => 25]);

        $row = $this->qb('users')->where('name', '=', 'Bob')->first();
        $this->assertNotNull($row);
        $this->assertEquals('Bob', $row['name']);
    }

    public function test_first_returns_null(): void
    {
        $row = $this->qb('users')->where('name', '=', 'Nobody')->first();
        $this->assertNull($row);
    }

    public function test_count(): void
    {
        $this->qb('users')->insert(['name' => 'Alice', 'email' => 'a@t.com', 'age' => 30]);
        $this->qb('users')->insert(['name' => 'Bob', 'email' => 'b@t.com', 'age' => 25]);

        $count = $this->qb('users')->count();
        $this->assertEquals(2, $count);

        $count = $this->qb('users')->where('age', '>', 26)->count();
        $this->assertEquals(1, $count);
    }

    public function test_update(): void
    {
        $this->qb('users')->insert(['name' => 'Alice', 'email' => 'a@t.com', 'age' => 30]);
        $this->qb('users')->where('name', '=', 'Alice')->update(['age' => 31]);

        $row = $this->qb('users')->where('name', '=', 'Alice')->first();
        $this->assertEquals(31, $row['age']);
    }

    public function test_delete(): void
    {
        $this->qb('users')->insert(['name' => 'Alice', 'email' => 'a@t.com', 'age' => 30]);
        $this->qb('users')->insert(['name' => 'Bob', 'email' => 'b@t.com', 'age' => 25]);

        $this->qb('users')->where('name', '=', 'Bob')->delete();

        $count = $this->qb('users')->count();
        $this->assertEquals(1, $count);
    }

    public function test_join(): void
    {
        $this->qb('users')->insert(['name' => 'Alice', 'email' => 'a@t.com', 'age' => 30]);
        $this->qb('posts')->insert(['user_id' => 1, 'title' => 'Hello', 'body' => 'World']);
        $this->qb('posts')->insert(['user_id' => 1, 'title' => 'Second', 'body' => 'Post']);

        $rows = $this->qb('users')
            ->select('users.name', 'posts.title')
            ->join('posts', 'users.id', '=', 'posts.user_id')
            ->get();

        $this->assertCount(2, $rows);
        $this->assertEquals('Alice', $rows[0]['name']);
    }

    public function test_group_by(): void
    {
        $this->qb('users')->insert(['name' => 'Alice', 'email' => 'a@t.com', 'age' => 30, 'active' => 1]);
        $this->qb('users')->insert(['name' => 'Bob', 'email' => 'b@t.com', 'age' => 25, 'active' => 1]);
        $this->qb('users')->insert(['name' => 'Carol', 'email' => 'c@t.com', 'age' => 35, 'active' => 0]);

        $rows = $this->qb('users')
            ->select('active', 'COUNT(*) as total')
            ->groupBy('active')
            ->orderBy('active', 'ASC')
            ->get();

        $this->assertCount(2, $rows);
        $this->assertEquals(1, $rows[0]['total']); // active=0 -> 1 user
        $this->assertEquals(2, $rows[1]['total']); // active=1 -> 2 users
    }

    public function test_insert_returns_last_insert_id(): void
    {
        $id = $this->qb('users')->insert(['name' => 'Alice', 'email' => 'a@t.com', 'age' => 30]);
        $this->assertEquals('1', $id);
    }
}
