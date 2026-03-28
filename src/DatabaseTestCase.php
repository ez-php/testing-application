<?php

declare(strict_types=1);

namespace EzPhp\Testing;

use EzPhp\Application\Application;
use EzPhp\Contracts\DatabaseInterface;
use PDO;

/**
 * Test case that wraps each test method in a database transaction.
 *
 * setUp() bootstraps the Application, resolves DatabaseInterface from the
 * container, and opens a transaction on the underlying PDO connection.
 * tearDown() rolls back the transaction unconditionally, discarding any
 * writes made during the test without requiring table truncation.
 *
 * The Application must be configured with a working database connection.
 * Override getBasePath() to point to an application root whose config/db.php
 * provides the correct connection details. For fast in-process tests, configure
 * a SQLite in-memory database (driver=sqlite, database=:memory:) via a custom
 * config file or service provider.
 *
 * @package EzPhp\Testing
 */
abstract class DatabaseTestCase extends ApplicationTestCase
{
    private PDO $pdo;

    /**
     * @return void
     * @throws \ReflectionException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $db = $this->app()->make(DatabaseInterface::class);
        $this->pdo = $db->getPdo();
        $this->pdo->beginTransaction();
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }

        parent::tearDown();
    }

    /**
     * Return the raw PDO connection used by the current test.
     *
     * @return PDO
     */
    protected function pdo(): PDO
    {
        return $this->pdo;
    }
}
