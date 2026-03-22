<?php

declare(strict_types=1);

namespace EzPhp\Testing;

use EzPhp\Http\Request;

/**
 * Test case that fires fake HTTP requests through the full application stack.
 *
 * Extends ApplicationTestCase — the Application is fully bootstrapped before
 * each test. Requests travel through all global middleware, the router, route-level
 * middleware, and the route handler. No actual HTTP server is involved.
 *
 * Use configureApplication() to register providers and routes before bootstrap.
 *
 * @package EzPhp\Testing
 */
abstract class HttpTestCase extends ApplicationTestCase
{
    /**
     * Send a GET request through the application.
     *
     * @param string               $uri
     * @param array<string, mixed> $headers
     *
     * @return TestResponse
     */
    protected function get(string $uri, array $headers = []): TestResponse
    {
        return $this->request('GET', $uri, [], $headers);
    }

    /**
     * Send a POST request through the application.
     *
     * @param string               $uri
     * @param array<string, mixed> $body
     * @param array<string, mixed> $headers
     *
     * @return TestResponse
     */
    protected function post(string $uri, array $body = [], array $headers = []): TestResponse
    {
        return $this->request('POST', $uri, $body, $headers);
    }

    /**
     * Send a PUT request through the application.
     *
     * @param string               $uri
     * @param array<string, mixed> $body
     * @param array<string, mixed> $headers
     *
     * @return TestResponse
     */
    protected function put(string $uri, array $body = [], array $headers = []): TestResponse
    {
        return $this->request('PUT', $uri, $body, $headers);
    }

    /**
     * Send a DELETE request through the application.
     *
     * @param string               $uri
     * @param array<string, mixed> $headers
     *
     * @return TestResponse
     */
    protected function delete(string $uri, array $headers = []): TestResponse
    {
        return $this->request('DELETE', $uri, [], $headers);
    }

    /**
     * Send an arbitrary HTTP request through the application stack.
     *
     * Header names are normalised to lowercase before being passed to the
     * Request constructor, matching the behaviour of RequestFactory.
     *
     * @param string               $method
     * @param string               $uri
     * @param array<string, mixed> $body
     * @param array<string, mixed> $headers
     *
     * @return TestResponse
     * @throws \ReflectionException
     */
    protected function request(string $method, string $uri, array $body = [], array $headers = []): TestResponse
    {
        $normalizedHeaders = [];

        foreach ($headers as $name => $value) {
            $normalizedHeaders[strtolower($name)] = $value;
        }

        $request = new Request(
            method: strtoupper($method),
            uri: $uri,
            body: $body,
            headers: $normalizedHeaders,
        );

        $response = $this->app()->handle($request);

        return new TestResponse($response);
    }
}
