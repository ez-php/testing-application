# ez-php/testing-application

Framework-coupled PHPUnit base classes for ez-php applications.

This package provides `ApplicationTestCase`, `DatabaseTestCase`, and `HttpTestCase` — test base classes that boot the full ez-php `Application` stack. It is the framework-aware companion to [`ez-php/testing`](https://github.com/ez-php/testing), which contains the framework-independent utilities (`TestResponse`, `EntityFactory`).

## Installation

```bash
composer require --dev ez-php/testing-application
```

## Base Classes

### ApplicationTestCase

Bootstraps a fresh `Application` instance before each test.

```php
use EzPhp\Testing\ApplicationTestCase;

final class MyTest extends ApplicationTestCase
{
    protected function configureApplication(Application $app): void
    {
        $app->register(MyServiceProvider::class);
    }

    public function testSomething(): void
    {
        $service = $this->app()->make(MyService::class);
        // ...
    }
}
```

### DatabaseTestCase

Extends `ApplicationTestCase`. Wraps each test in a database transaction that is rolled back on teardown — no table truncation needed.

```php
use EzPhp\Testing\DatabaseTestCase;

final class UserRepositoryTest extends DatabaseTestCase
{
    protected function getBasePath(): string
    {
        // Return path to an app root with config/db.php
    }

    public function testInsert(): void
    {
        $this->pdo()->exec("INSERT INTO users (name) VALUES ('Alice')");
        // rolled back automatically after the test
    }
}
```

### HttpTestCase

Extends `ApplicationTestCase`. Dispatches fake HTTP requests through the full middleware and routing stack — no HTTP server required.

```php
use EzPhp\Testing\HttpTestCase;

final class ApiTest extends HttpTestCase
{
    protected function configureApplication(Application $app): void
    {
        $app->register(ApiRouteProvider::class);
    }

    public function testGetUser(): void
    {
        $this->get('/users/1')->assertOk()->assertJson(['id' => 1]);
    }
}
```

## MigrationBootstrap / SeederBootstrap

Both are one-shot utilities for a test-suite **bootstrap script** (e.g. `phpunit.xml`'s
`<bootstrap>`), not per-test base classes — they boot a fresh `Application` against the
test database and run the equivalent console command (`ez migrate` / `ez db:seed`) once,
before any test runs:

```php
// bootstrap/test-setup.php
require __DIR__ . '/../vendor/autoload.php';

use EzPhp\Testing\MigrationBootstrap;
use EzPhp\Testing\SeederBootstrap;

MigrationBootstrap::run(__DIR__ . '/..');
SeederBootstrap::run(__DIR__ . '/..');
```

```xml
<!-- phpunit.xml -->
<phpunit bootstrap="bootstrap/test-setup.php">
```

Both swap `DB_DATABASE` for `DB_TESTING_DATABASE` first (when the latter is set), so
migrations/seeders run against the test schema, not production. A non-zero exit code from
the underlying command is thrown as a `RuntimeException`, failing the suite immediately
with a clear message rather than letting every test fail against an unmigrated/unseeded
database.

## Requirements

- PHP 8.5+
- ez-php/framework
- ez-php/testing (for `TestResponse`)
