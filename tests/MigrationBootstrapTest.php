<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Testing\MigrationBootstrap;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for MigrationBootstrap.
 *
 * Verifies that switchToTestDatabase() correctly promotes DB_TESTING_DATABASE
 * to DB_DATABASE in all environment sources, and that run() exercises the
 * bootstrap without throwing on a minimal SQLite application.
 *
 * @package Tests
 */
#[CoversClass(MigrationBootstrap::class)]
final class MigrationBootstrapTest extends TestCase
{
    /** @var array<string, string|false> Original environment values restored in tearDown */
    private array $originalEnv = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Snapshot the env vars we may mutate so tearDown can restore them.
        foreach (['DB_DATABASE', 'DB_TESTING_DATABASE'] as $key) {
            $this->originalEnv[$key] = getenv($key);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->originalEnv as $key => $original) {
            if ($original === false) {
                putenv($key);
                unset($_ENV[$key], $_SERVER[$key]);
            } else {
                putenv("$key=$original");
                $_ENV[$key] = $original;
                $_SERVER[$key] = $original;
            }
        }

        parent::tearDown();
    }

    // ─── switchToTestDatabase (via run) ───────────────────────────────────────

    /**
     * When DB_TESTING_DATABASE is set, DB_DATABASE should be replaced with its value
     * in putenv, $_ENV, and $_SERVER before the Application boots.
     *
     * We test the side effect on the environment rather than run() end-to-end
     * to avoid needing a full application scaffold with migrations directory.
     *
     * @return void
     */
    public function testSwitchToTestDatabaseUpdatesAllEnvSources(): void
    {
        putenv('DB_DATABASE=production_db');
        putenv('DB_TESTING_DATABASE=test_db');
        $_ENV['DB_DATABASE'] = 'production_db';
        $_ENV['DB_TESTING_DATABASE'] = 'test_db';
        $_SERVER['DB_DATABASE'] = 'production_db';
        $_SERVER['DB_TESTING_DATABASE'] = 'test_db';

        // Invoke switchToTestDatabase indirectly by calling run() on a minimal
        // basePath. We expect it to update the environment before booting, so
        // after a (failing) run we can still inspect the environment.
        // To avoid relying on a full framework boot, we call the static method
        // through reflection to access the private helper directly.
        $ref = new \ReflectionClass(MigrationBootstrap::class);
        $method = $ref->getMethod('switchToTestDatabase');
        $method->invoke(null);

        $this->assertSame('test_db', getenv('DB_DATABASE'));
        $this->assertSame('test_db', $_ENV['DB_DATABASE']);
        $this->assertSame('test_db', $_SERVER['DB_DATABASE']);
    }

    /**
     * When DB_TESTING_DATABASE is absent, DB_DATABASE must be left untouched.
     *
     * @return void
     */
    public function testSwitchToTestDatabaseIsNoOpWhenTestDbNotSet(): void
    {
        putenv('DB_DATABASE=production_db');
        putenv('DB_TESTING_DATABASE');   // unset
        $_ENV['DB_DATABASE'] = 'production_db';
        unset($_ENV['DB_TESTING_DATABASE'], $_SERVER['DB_TESTING_DATABASE']);

        $ref = new \ReflectionClass(MigrationBootstrap::class);
        $method = $ref->getMethod('switchToTestDatabase');
        $method->invoke(null);

        $this->assertSame('production_db', getenv('DB_DATABASE'));
        $this->assertSame('production_db', $_ENV['DB_DATABASE']);
    }

    /**
     * When DB_TESTING_DATABASE is empty string, DB_DATABASE must be left untouched.
     *
     * @return void
     */
    public function testSwitchToTestDatabaseIsNoOpWhenTestDbIsEmpty(): void
    {
        putenv('DB_DATABASE=production_db');
        putenv('DB_TESTING_DATABASE=');
        $_ENV['DB_DATABASE'] = 'production_db';
        $_ENV['DB_TESTING_DATABASE'] = '';
        $_SERVER['DB_DATABASE'] = 'production_db';
        $_SERVER['DB_TESTING_DATABASE'] = '';

        $ref = new \ReflectionClass(MigrationBootstrap::class);
        $method = $ref->getMethod('switchToTestDatabase');
        $method->invoke(null);

        $this->assertSame('production_db', getenv('DB_DATABASE'));
        $this->assertSame('production_db', $_ENV['DB_DATABASE']);
    }

    // ─── run() with minimal SQLite app ────────────────────────────────────────

    /**
     * run() must complete without throwing when given a minimal SQLite application.
     * Uses a SQLite :memory: database so no external MySQL instance is required.
     *
     * @return void
     */
    public function testRunMigratesWithSqliteApp(): void
    {
        $basePath = $this->buildSqliteBasePath();

        putenv('DB_TESTING_DATABASE=');   // disable swap — use the config directly
        unset($_ENV['DB_TESTING_DATABASE'], $_SERVER['DB_TESTING_DATABASE']);

        MigrationBootstrap::run($basePath);

        // If we reach here, run() completed without exception.
        $this->addToAssertionCount(1);

        $this->cleanUp($basePath);
    }

    // ─── helpers ─────────────────────────────────────────────────────────────

    /**
     * Create a minimal application base path with SQLite config and an empty migrations directory.
     *
     * @return string
     */
    private function buildSqliteBasePath(): string
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ez-php-mb-test-' . uniqid('', true);
        mkdir($path . DIRECTORY_SEPARATOR . 'config', 0o777, true);
        mkdir($path . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations', 0o777, true);

        file_put_contents(
            $path . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'db.php',
            "<?php\n\ndeclare(strict_types=1);\n\nreturn ['driver' => 'sqlite', 'database' => ':memory:'];\n",
        );

        return $path;
    }

    /**
     * Recursively remove a temporary directory.
     *
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
