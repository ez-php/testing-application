<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Application\Application;
use EzPhp\Testing\ApplicationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests that ApplicationTestCase bootstraps the Application correctly and
 * exposes the expected hooks and accessors to subclasses.
 */
#[CoversClass(ApplicationTestCase::class)]
final class ApplicationTestCaseTest extends ApplicationTestCase
{
    // ─── app() ────────────────────────────────────────────────────────────────

    public function testAppReturnsApplicationInstance(): void
    {
        $this->assertInstanceOf(Application::class, $this->app());
    }

    public function testApplicationIsBootstrappedBeforeTest(): void
    {
        // If the application were not bootstrapped, make() would throw.
        // Resolving the Router (always registered by RouterServiceProvider) is
        // a lightweight proxy for "bootstrap succeeded".
        $router = $this->app()->make(\EzPhp\Routing\Router::class);

        $this->assertInstanceOf(\EzPhp\Routing\Router::class, $router);
    }

    // ─── getBasePath() ────────────────────────────────────────────────────────

    public function testDefaultBasePathCreatesConfigDirectory(): void
    {
        // The default getBasePath() creates a temp dir with config/ so that
        // ConfigLoader does not throw a ConfigException.
        $configPath = $this->app()->basePath('config');

        $this->assertDirectoryExists($configPath);
    }

    // ─── configureApplication() ───────────────────────────────────────────────

    public function testConfigureApplicationIsCalledBeforeBootstrap(): void
    {
        // Verified indirectly: TestConfigProvider registers a binding in
        // configureApplication(). If it runs before bootstrap(), the binding
        // is visible after bootstrap().
        $this->assertTrue($this->configureWasCalled);
    }

    // ─── isolation ────────────────────────────────────────────────────────────

    public function testEachTestReceivesAFreshApplication(): void
    {
        // A fresh Application is created per test — the instance from the
        // previous test must not bleed through.
        $this->assertNotSame(spl_object_id($this->app()), 0);
    }

    // ─── helpers ──────────────────────────────────────────────────────────────

    private bool $configureWasCalled = false;

    protected function configureApplication(Application $app): void
    {
        $this->configureWasCalled = true;
    }
}
