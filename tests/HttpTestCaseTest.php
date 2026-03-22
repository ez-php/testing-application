<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Application\Application;
use EzPhp\Contracts\ServiceProvider;
use EzPhp\Routing\Router;
use EzPhp\Testing\ApplicationTestCase;
use EzPhp\Testing\HttpTestCase;
use EzPhp\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

/**
 * Tests that HttpTestCase fires requests through the full stack and returns
 * TestResponse objects with correct status, body, and headers.
 */
#[CoversClass(HttpTestCase::class)]
#[UsesClass(ApplicationTestCase::class)]
#[UsesClass(TestResponse::class)]
final class HttpTestCaseTest extends HttpTestCase
{
    protected function configureApplication(Application $app): void
    {
        $app->register(HttpTestRouteProvider::class);
    }

    // ─── get ──────────────────────────────────────────────────────────────────

    public function testGetReturnsTestResponse(): void
    {
        $response = $this->get('/hello');

        $this->assertInstanceOf(TestResponse::class, $response);
    }

    public function testGetReturnsCorrectBody(): void
    {
        $this->get('/hello')->assertOk()->assertSee('Hello World');
    }

    // ─── post ─────────────────────────────────────────────────────────────────

    public function testPostSendsBodyParameters(): void
    {
        $this->post('/echo', ['message' => 'hi'])
            ->assertOk()
            ->assertSee('hi');
    }

    // ─── put ──────────────────────────────────────────────────────────────────

    public function testPutReturnsCorrectStatus(): void
    {
        $this->put('/update', ['value' => 'new'])->assertOk();
    }

    // ─── delete ───────────────────────────────────────────────────────────────

    public function testDeleteReturnsCorrectStatus(): void
    {
        $this->delete('/remove')->assertOk();
    }

    // ─── headers ──────────────────────────────────────────────────────────────

    public function testCustomHeadersArePassed(): void
    {
        $response = $this->get('/headers', ['X-Test-Header' => 'value123']);

        $response->assertOk()->assertSee('value123');
    }

    // ─── 404 ──────────────────────────────────────────────────────────────────

    public function testUnknownRouteReturnsNotFound(): void
    {
        $this->get('/this-route-does-not-exist')->assertNotFound();
    }

    // ─── request() ────────────────────────────────────────────────────────────

    public function testRequestNormalizesHeaderNamesToLowercase(): void
    {
        // The /headers route reads the 'x-test-header' key (lowercase).
        // Even when supplied with mixed-case names, request() must normalise them.
        $response = $this->request('GET', '/headers', [], ['X-Test-Header' => 'normalised']);

        $response->assertOk()->assertSee('normalised');
    }
}

/**
 * Registers test routes on the Router so HttpTestCaseTest has something to dispatch against.
 */
final class HttpTestRouteProvider extends ServiceProvider
{
    /**
     * @return void
     */
    public function register(): void
    {
    }

    /**
     * @return void
     */
    public function boot(): void
    {
        $router = $this->app->make(Router::class);

        $router->get('/hello', fn () => 'Hello World');

        $router->post('/echo', function (\EzPhp\Http\Request $request): string {
            $message = $request->input('message', '');

            return is_string($message) ? $message : '';
        });

        $router->put('/update', fn () => 'updated');

        $router->delete('/remove', fn () => 'removed');

        $router->get('/headers', function (\EzPhp\Http\Request $request): string {
            $value = $request->header('x-test-header', '');

            return is_string($value) ? $value : '';
        });
    }
}
