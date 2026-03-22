<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Testing\ApplicationTestCase;
use EzPhp\Testing\DatabaseTestCase;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

/**
 * Tests that DatabaseTestCase wraps each test method in a database transaction
 * and rolls back on teardown.
 *
 * Uses a SQLite :memory: database by providing a config/db.php file in a
 * temporary base path — no external MySQL instance required.
 */
#[CoversClass(DatabaseTestCase::class)]
#[UsesClass(ApplicationTestCase::class)]
final class DatabaseTestCaseTest extends DatabaseTestCase
{
    protected function getBasePath(): string
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ez-php-dbtest-' . uniqid('', true);
        mkdir($path . DIRECTORY_SEPARATOR . 'config', 0o777, true);

        file_put_contents(
            $path . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'db.php',
            "<?php\n\ndeclare(strict_types=1);\n\nreturn ['driver' => 'sqlite', 'database' => ':memory:'];\n",
        );

        return $path;
    }

    // ─── pdo() ────────────────────────────────────────────────────────────────

    public function testPdoReturnsPdoInstance(): void
    {
        $this->assertInstanceOf(PDO::class, $this->pdo());
    }

    // ─── transaction ──────────────────────────────────────────────────────────

    public function testTransactionIsActiveduringTest(): void
    {
        $this->assertTrue($this->pdo()->inTransaction());
    }

    public function testDatabaseIsAccessible(): void
    {
        $pdo = $this->pdo();

        $pdo->exec('CREATE TABLE IF NOT EXISTS txn_test (id INTEGER PRIMARY KEY, val TEXT)');
        $pdo->exec("INSERT INTO txn_test (val) VALUES ('hello')");

        $stmt = $pdo->query('SELECT val FROM txn_test');
        $this->assertInstanceOf(\PDOStatement::class, $stmt);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $this->assertSame(['hello'], $rows);
    }

    public function testWritesWithinTransactionAreVisible(): void
    {
        $pdo = $this->pdo();

        $pdo->exec('CREATE TABLE IF NOT EXISTS visibility_test (id INTEGER PRIMARY KEY, n INTEGER)');

        for ($i = 1; $i <= 3; $i++) {
            $pdo->exec("INSERT INTO visibility_test (n) VALUES ($i)");
        }

        $stmt = $pdo->query('SELECT COUNT(*) FROM visibility_test');
        $this->assertInstanceOf(\PDOStatement::class, $stmt);
        $count = (int) $stmt->fetchColumn();

        $this->assertSame(3, $count);
    }
}
