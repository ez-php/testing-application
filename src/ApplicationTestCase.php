<?php

declare(strict_types=1);

namespace EzPhp\Testing;

use EzPhp\Application\Application;
use PHPUnit\Framework\TestCase;

/**
 * Base test case that boots the Application for each test method.
 *
 * Subclasses override getBasePath() to point to their application root.
 * A temporary directory with an empty config/ stub is provided by default,
 * which allows tests that do not need real configuration to run without setup.
 *
 * Override configureApplication() to register service providers, middleware,
 * commands, or routes before bootstrap() is called.
 *
 * @package EzPhp\Testing
 */
abstract class ApplicationTestCase extends TestCase
{
    private Application $application;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->application = new Application($this->getBasePath());
        $this->configureApplication($this->application);
        $this->application->bootstrap();
    }

    /**
     * Return the absolute path to the application root.
     *
     * The default implementation creates a temporary directory with an empty
     * config/ subdirectory so that ConfigLoader does not throw when no real
     * configuration directory is available. Tests that need real configuration
     * (database, routes, etc.) must override this method.
     *
     * @return string
     */
    protected function getBasePath(): string
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ez-php-testing-' . uniqid('', true);
        mkdir($path . DIRECTORY_SEPARATOR . 'config', 0o777, true);

        return $path;
    }

    /**
     * Hook called with the fresh Application instance before bootstrap().
     *
     * Override to register service providers, middleware, commands, or routes.
     * At this point the Application has not been bootstrapped — do not call
     * make() or resolve services here; use configureApplication() only to
     * call register() and middleware() on the Application.
     *
     * @param Application $app
     *
     * @return void
     */
    protected function configureApplication(Application $app): void
    {
    }

    /**
     * Return the fully bootstrapped Application under test.
     *
     * @return Application
     */
    protected function app(): Application
    {
        return $this->application;
    }
}
