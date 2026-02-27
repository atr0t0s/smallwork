<?php
// tests/Unit/Database/MigrationTest.php
declare(strict_types=1);
namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use Smallwork\Database\Connection;
use Smallwork\Database\Adapters\PdoAdapter;
use Smallwork\Database\Schema;
use Smallwork\Database\Migrator;

class MigrationTest extends TestCase
{
    private PdoAdapter $db;
    private string $migrationsDir;

    protected function setUp(): void
    {
        $this->db = Connection::create(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->migrationsDir = sys_get_temp_dir() . '/sw_migrations_' . uniqid();
        mkdir($this->migrationsDir, 0755, true);
    }

    protected function tearDown(): void
    {
        // Clean up temp migration files
        foreach (glob($this->migrationsDir . '/*.php') as $file) {
            unlink($file);
        }
        rmdir($this->migrationsDir);
    }

    public function test_schema_creates_table(): void
    {
        $schema = new Schema($this->db);
        $schema->create('users', function (Schema $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->integer('age')->nullable();
            $table->timestamps();
        });

        // Verify table exists by inserting
        $this->db->execute("INSERT INTO users (name, email, age) VALUES (?, ?, ?)", ['Alice', 'a@b.com', 30]);
        $rows = $this->db->fetchAll('SELECT * FROM users');
        $this->assertCount(1, $rows);
        $this->assertArrayHasKey('id', $rows[0]);
        $this->assertArrayHasKey('created_at', $rows[0]);
        $this->assertArrayHasKey('updated_at', $rows[0]);
    }

    public function test_schema_drops_table(): void
    {
        $schema = new Schema($this->db);
        $schema->create('temp', function (Schema $table) {
            $table->id();
            $table->string('name');
        });
        $schema->drop('temp');

        $this->expectException(\PDOException::class);
        $this->db->fetchAll('SELECT * FROM temp');
    }

    public function test_schema_column_types(): void
    {
        $schema = new Schema($this->db);
        $schema->create('items', function (Schema $table) {
            $table->id();
            $table->string('title', 200);
            $table->text('body');
            $table->integer('quantity');
            $table->boolean('active');
            $table->float('price');
        });

        $this->db->execute(
            "INSERT INTO items (title, body, quantity, active, price) VALUES (?, ?, ?, ?, ?)",
            ['Widget', 'A fine widget', 10, 1, 9.99]
        );
        $row = $this->db->fetchOne('SELECT * FROM items WHERE id = 1');
        $this->assertEquals('Widget', $row['title']);
    }

    public function test_migrator_runs_migrations(): void
    {
        // Create two migration files
        file_put_contents($this->migrationsDir . '/001_create_users.php', '<?php
use Smallwork\Database\Schema;
return new class {
    public function up(Schema $schema): void {
        $schema->create("users", function (Schema $table) {
            $table->id();
            $table->string("name");
        });
    }
    public function down(Schema $schema): void {
        $schema->drop("users");
    }
};');

        file_put_contents($this->migrationsDir . '/002_create_posts.php', '<?php
use Smallwork\Database\Schema;
return new class {
    public function up(Schema $schema): void {
        $schema->create("posts", function (Schema $table) {
            $table->id();
            $table->string("title");
        });
    }
    public function down(Schema $schema): void {
        $schema->drop("posts");
    }
};');

        $migrator = new Migrator($this->db, $this->migrationsDir);
        $ran = $migrator->migrate();

        $this->assertEquals(2, $ran);

        // Verify tables exist
        $this->db->execute("INSERT INTO users (name) VALUES (?)", ['Alice']);
        $this->db->execute("INSERT INTO posts (title) VALUES (?)", ['Hello']);
        $this->assertCount(1, $this->db->fetchAll('SELECT * FROM users'));
        $this->assertCount(1, $this->db->fetchAll('SELECT * FROM posts'));
    }

    public function test_migrator_tracks_ran_migrations(): void
    {
        file_put_contents($this->migrationsDir . '/001_create_test.php', '<?php
use Smallwork\Database\Schema;
return new class {
    public function up(Schema $schema): void {
        $schema->create("test_table", function (Schema $table) {
            $table->id();
            $table->string("name");
        });
    }
    public function down(Schema $schema): void {
        $schema->drop("test_table");
    }
};');

        $migrator = new Migrator($this->db, $this->migrationsDir);
        $ran1 = $migrator->migrate();
        $ran2 = $migrator->migrate(); // Run again - should skip already-run

        $this->assertEquals(1, $ran1);
        $this->assertEquals(0, $ran2);
    }

    public function test_migrator_rollback(): void
    {
        file_put_contents($this->migrationsDir . '/001_create_rollback_test.php', '<?php
use Smallwork\Database\Schema;
return new class {
    public function up(Schema $schema): void {
        $schema->create("rollback_test", function (Schema $table) {
            $table->id();
            $table->string("name");
        });
    }
    public function down(Schema $schema): void {
        $schema->drop("rollback_test");
    }
};');

        $migrator = new Migrator($this->db, $this->migrationsDir);
        $migrator->migrate();

        // Verify table exists
        $this->db->execute("INSERT INTO rollback_test (name) VALUES (?)", ['test']);

        $rolled = $migrator->rollback();
        $this->assertEquals(1, $rolled);

        // Table should be gone
        $this->expectException(\PDOException::class);
        $this->db->fetchAll('SELECT * FROM rollback_test');
    }
}
