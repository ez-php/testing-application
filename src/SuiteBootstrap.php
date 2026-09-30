<?php

declare(strict_types=1);

namespace EzPhp\Testing;

use EzPhp\Application\Application;
use EzPhp\Contracts\ServiceProvider;
use RuntimeException;

/**
 * Class SuiteBootstrap
 *
 * Shared boot sequence for MigrationBootstrap and SeederBootstrap: switch to the
 * test database, then boot the Application with the application's module
 * providers from `provider/modules.php`.
 *
 * The module providers matter: `ez migrate` resolves `SchemaInterface`, which
 * only a module provider (ez-php/orm's SchemaServiceProvider) binds, and seeders
 * usually need application bindings. Booting with the core providers alone made
 * any pending migration fail with "No SchemaInterface configured".
 *
 * @internal
 * @package EzPhp\Testing
 */
final class SuiteBootstrap
{
    /**
     * Switch to the test database and return a bootstrapped Application.
     *
     * @param string $basePath Absolute path to the application root.
     *
     * @return Application
     */
    public static function boot(string $basePath): Application
    {
        self::switchToTestDatabase();

        $app = new Application($basePath);

        foreach (self::moduleProviders($basePath) as $provider) {
            $app->register($provider);
        }

        $app->bootstrap();

        return $app;
    }

    /**
     * Replace DB_DATABASE with the value of DB_TESTING_DATABASE in all environment sources.
     *
     * No-op when DB_TESTING_DATABASE is not set or is an empty string.
     *
     * @return void
     */
    public static function switchToTestDatabase(): void
    {
        $raw = $_ENV['DB_TESTING_DATABASE']
            ?? $_SERVER['DB_TESTING_DATABASE']
            ?? getenv('DB_TESTING_DATABASE');

        $testDb = is_string($raw) ? $raw : '';

        if ($testDb === '') {
            return;
        }

        putenv('DB_DATABASE=' . $testDb);
        $_ENV['DB_DATABASE'] = $testDb;
        $_SERVER['DB_DATABASE'] = $testDb;
    }

    /**
     * The provider classes listed in `<basePath>/provider/modules.php`, or none
     * when the file does not exist.
     *
     * @param string $basePath
     *
     * @return list<class-string<ServiceProvider>>
     *
     * @throws RuntimeException When the file does not return a list of service provider classes.
     */
    private static function moduleProviders(string $basePath): array
    {
        $file = $basePath . '/provider/modules.php';

        if (!is_file($file)) {
            return [];
        }

        /** @var mixed $providers */
        $providers = require $file;

        if (!is_array($providers) || !array_is_list($providers)) {
            throw new RuntimeException("{$file} must return a list of service provider class names.");
        }

        $classes = [];

        foreach ($providers as $provider) {
            if (!is_string($provider) || !is_subclass_of($provider, ServiceProvider::class)) {
                throw new RuntimeException(
                    "{$file} must return a list of service provider class names; got " . var_export($provider, true) . '.'
                );
            }

            $classes[] = $provider;
        }

        return $classes;
    }
}
