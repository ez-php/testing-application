<?php

declare(strict_types=1);

namespace EzPhp\Testing;

use EzPhp\Console\Console;
use RuntimeException;

/**
 * Class MigrationBootstrap
 *
 * Utility that boots the Application against the test database and runs all
 * pending migrations. Intended for use in test suite bootstrap scripts
 * (e.g. phpunit.xml <bootstrap> or a dedicated bootstrap.php file).
 *
 * It swaps DB_DATABASE for DB_TESTING_DATABASE before booting so that
 * migrations are applied to the test schema rather than the production schema.
 * Both environment super-globals ($_ENV, $_SERVER) and the process environment
 * (getenv / putenv) are updated for full compatibility with framework config loaders.
 * The providers listed in `provider/modules.php` are registered before booting
 * (see SuiteBootstrap), so module bindings such as ez-php/orm's SchemaInterface
 * are available to the migrations.
 *
 * Usage in phpunit.xml:
 *   <bootstrap>bootstrap/migrate.php</bootstrap>
 *
 * Example bootstrap/migrate.php:
 *   <?php
 *   require __DIR__ . '/../vendor/autoload.php';
 *   MigrationBootstrap::run(__DIR__ . '/..');
 *
 * @package EzPhp\Testing
 */
final class MigrationBootstrap
{
    /**
     * Boot the Application with the test database and run all pending migrations.
     *
     * @param string $basePath Absolute path to the application root (contains config/, database/).
     *
     * @return void
     */
    public static function run(string $basePath): void
    {
        $app = SuiteBootstrap::boot($basePath);

        // Runs through the same `ez migrate` console command a developer would
        // invoke by hand, rather than resolving framework/src/Migration/Migrator
        // directly — Migrator is marked @internal, so calling it from a sibling
        // package would defeat the point of that marker (freedom for the
        // framework to change or remove it without notice).
        //
        // Output is buffered and discarded to preserve this method's original
        // silent behaviour (a test-suite bootstrap script, not an interactive
        // CLI invocation) — only a non-zero exit code surfaces, as an exception.
        ob_start();

        try {
            $exitCode = $app->make(Console::class)->run(['ez', 'migrate']);
        } finally {
            ob_end_clean();
        }

        if ($exitCode !== 0) {
            throw new RuntimeException("`ez migrate` failed with exit code {$exitCode} during test bootstrap.");
        }
    }
}
