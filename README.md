# ez-php/testing-application

Framework-coupled PHPUnit base classes for ez-php applications.

This package provides `ApplicationTestCase`, `DatabaseTestCase`, and `HttpTestCase` — test base classes that boot the full ez-php `Application` stack. It is the framework-aware companion to [`ez-php/testing`](https://github.com/ez-php/testing), which contains the framework-independent utilities (`TestResponse`, `ModelFactory`).

## Installation

```bash
composer require-dev ez-php/testing-application
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

## Requirements

- PHP 8.5+
- ez-php/framework
- ez-php/testing (for `TestResponse`)
