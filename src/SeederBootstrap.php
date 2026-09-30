<?php

declare(strict_types=1);

namespace EzPhp\Testing;

use EzPhp\Console\Console;
use RuntimeException;

/**
 * Class SeederBootstrap
 *
 * Utility that boots the Application against the test database and runs all
 * database seeders. Companion to MigrationBootstrap — framework core
 * documents `db:seed` as a first-class console command, but until now no
 * bootstrap helper ran it the way MigrationBootstrap::run() runs `migrate`,
 * so every application re-invented suite-level seeding by hand.
 *
 * Like MigrationBootstrap, it swaps DB_DATABASE for DB_TESTING_DATABASE
 * before booting, so seeders run against the test schema, and registers the
 * providers from `provider/modules.php` so seeders see the module bindings.
 *
 * Usage — typically chained after MigrationBootstrap in a bootstrap script:
 *   <?php
 *   require __DIR__ . '/../vendor/autoload.php';
 *   MigrationBootstrap::run(__DIR__ . '/..');
 *   SeederBootstrap::run(__DIR__ . '/..');
 *
 * @package EzPhp\Testing
 */
final class SeederBootstrap
{
    /**
     * Boot the Application with the test database and run all database seeders.
     *
     * @param string $basePath Absolute path to the application root (contains config/, database/).
     *
     * @return void
     */
    public static function run(string $basePath): void
    {
        $app = SuiteBootstrap::boot($basePath);

        // Runs through the same `ez db:seed` console command a developer would
        // invoke by hand, rather than resolving framework/src/Migration/SeederRunner
        // directly — same reasoning as MigrationBootstrap's use of `ez migrate`.
        //
        // Output is buffered and discarded to preserve a silent bootstrap-script
        // contract — only a non-zero exit code surfaces, as an exception.
        ob_start();

        try {
            $exitCode = $app->make(Console::class)->run(['ez', 'db:seed']);
        } finally {
            ob_end_clean();
        }

        if ($exitCode !== 0) {
            throw new RuntimeException("`ez db:seed` failed with exit code {$exitCode} during test bootstrap.");
        }
    }
}
