<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Testing\SeederBootstrap;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

/**
 * Tests for SeederBootstrap.
 *
 * Mirrors MigrationBootstrapTest's structure (minimal SQLite application,
 * env-restoring helpers), but goes one step further: it queries the database
 * file after run() to confirm the seeder actually executed, not just that
 * run() returned without throwing.
 *
 * @package Tests
 */
#[CoversClass(SeederBootstrap::class)]
final class SeederBootstrapTest extends TestCase
{
    public function testRunExecutesSeedersWithSqliteApp(): void
    {
        $basePath = $this->buildSqliteBasePath();
        $dbFile = $basePath . DIRECTORY_SEPARATOR . 'test.sqlite';

        file_put_contents(
            $basePath . '/database/seeders/ASeeder.php',
            <<<'PHP'
                <?php
                use EzPhp\Database\Database;
                use EzPhp\Migration\SeederInterface;
                return new class implements SeederInterface {
                    public function run(Database $db): void {
                        $db->execute('INSERT INTO items (name) VALUES (?)', ['Alice']);
                    }
                };
                PHP,
        );

        SeederBootstrap::run($basePath);

        $pdo = new \PDO('sqlite:' . $dbFile);
        $stmt = $pdo->query('SELECT name FROM items');
        $this->assertInstanceOf(\PDOStatement::class, $stmt);

        $this->assertSame(['Alice'], $stmt->fetchAll(\PDO::FETCH_COLUMN));

        $this->cleanUp($basePath);
    }

    public function testRunThrowsWhenSeederFails(): void
    {
        $basePath = $this->buildSqliteBasePath();

        file_put_contents(
            $basePath . '/database/seeders/FailingSeeder.php',
            <<<'PHP'
                <?php
                use EzPhp\Database\Database;
                use EzPhp\Migration\SeederInterface;
                return new class implements SeederInterface {
                    public function run(Database $db): void {
                        throw new \RuntimeException('boom');
                    }
                };
                PHP,
        );

        $this->expectException(RuntimeException::class);

        try {
            SeederBootstrap::run($basePath);
        } finally {
            $this->cleanUp($basePath);
        }
    }

    /**
     * Create a minimal application base path with SQLite config, an empty
     * migrations directory, and an `items` table pre-created via config so a
     * seeder can insert into it without needing an actual migration to run.
     *
     * @return string
     */
    private function buildSqliteBasePath(): string
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ez-php-sb-test-' . uniqid('', true);
        mkdir($path . DIRECTORY_SEPARATOR . 'config', 0o777, true);
        mkdir($path . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations', 0o777, true);
        mkdir($path . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'seeders', 0o777, true);

        $dbFile = $path . DIRECTORY_SEPARATOR . 'test.sqlite';

        file_put_contents(
            $path . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'db.php',
            "<?php\n\ndeclare(strict_types=1);\n\nreturn ['driver' => 'sqlite', 'database' => '{$dbFile}'];\n",
        );

        $pdo = new \PDO('sqlite:' . $dbFile);
        $pdo->exec('CREATE TABLE items (name TEXT)');

        return $path;
    }

    /**
     * @param string $path
     *
     * @return void
     */
    private function cleanUp(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full = $path . DIRECTORY_SEPARATOR . $item;
            is_dir($full) ? $this->cleanUp($full) : unlink($full);
        }

        rmdir($path);
    }
}
