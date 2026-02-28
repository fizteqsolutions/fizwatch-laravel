<?php

namespace FizWatch\Tests\Feature;

use FizWatch\FizWatch;
use Orchestra\Testbench\TestCase;

/**
 * Tests for the private filtering methods on FizWatch.
 *
 * Since PHPUnit runs in console mode, `buildRequest()` returns null and
 * request filtering can't be tested end-to-end. We test the filter logic
 * directly via reflection instead.
 */
class RequestFilteringTest extends TestCase
{
    private function callPrivateMethod(object $object, string $method, array $args = []): mixed
    {
        $reflection = new \ReflectionMethod($object, $method);

        return $reflection->invoke($object, ...$args);
    }

    public function test_filter_fields_replaces_sensitive_values(): void
    {
        $fizwatch = new FizWatch(
            url: 'https://fizwatch.test',
            key: 'fiz_abc123',
            sensitiveFields: ['password', '_token'],
        );

        $result = $this->callPrivateMethod($fizwatch, 'filterFields', [[
            'username' => 'john',
            'password' => 'secret123',
            '_token' => 'csrf-token',
        ]]);

        $this->assertEquals('john', $result['username']);
        $this->assertEquals('[Filtered]', $result['password']);
        $this->assertEquals('[Filtered]', $result['_token']);
    }

    public function test_filter_fields_is_case_insensitive(): void
    {
        $fizwatch = new FizWatch(
            url: 'https://fizwatch.test',
            key: 'fiz_abc123',
            sensitiveFields: ['password'],
        );

        $result = $this->callPrivateMethod($fizwatch, 'filterFields', [[
            'Password' => 'secret',
            'PASSWORD' => 'also-secret',
            'password' => 'and-secret',
        ]]);

        $this->assertEquals('[Filtered]', $result['Password']);
        $this->assertEquals('[Filtered]', $result['PASSWORD']);
        $this->assertEquals('[Filtered]', $result['password']);
    }

    public function test_filter_fields_handles_nested_arrays(): void
    {
        $fizwatch = new FizWatch(
            url: 'https://fizwatch.test',
            key: 'fiz_abc123',
            sensitiveFields: ['password'],
        );

        $result = $this->callPrivateMethod($fizwatch, 'filterFields', [[
            'user' => [
                'name' => 'john',
                'password' => 'secret',
                'profile' => [
                    'password' => 'nested-secret',
                ],
            ],
        ]]);

        $this->assertEquals('john', $result['user']['name']);
        $this->assertEquals('[Filtered]', $result['user']['password']);
        $this->assertEquals('[Filtered]', $result['user']['profile']['password']);
    }

    public function test_filter_fields_preserves_non_sensitive_values(): void
    {
        $fizwatch = new FizWatch(
            url: 'https://fizwatch.test',
            key: 'fiz_abc123',
            sensitiveFields: ['password'],
        );

        $result = $this->callPrivateMethod($fizwatch, 'filterFields', [[
            'name' => 'john',
            'email' => 'john@example.com',
        ]]);

        $this->assertEquals('john', $result['name']);
        $this->assertEquals('john@example.com', $result['email']);
    }

    public function test_filter_headers_replaces_sensitive_headers(): void
    {
        $fizwatch = new FizWatch(
            url: 'https://fizwatch.test',
            key: 'fiz_abc123',
            sensitiveHeaders: ['authorization', 'cookie'],
        );

        $result = $this->callPrivateMethod($fizwatch, 'filterHeaders', [[
            'authorization' => ['Bearer token123'],
            'cookie' => ['session=abc'],
            'content-type' => ['application/json'],
        ]]);

        $this->assertEquals('[Filtered]', $result['authorization']);
        $this->assertEquals('[Filtered]', $result['cookie']);
        $this->assertEquals('application/json', $result['content-type']);
    }

    public function test_filter_headers_is_case_insensitive(): void
    {
        $fizwatch = new FizWatch(
            url: 'https://fizwatch.test',
            key: 'fiz_abc123',
            sensitiveHeaders: ['authorization'],
        );

        $result = $this->callPrivateMethod($fizwatch, 'filterHeaders', [[
            'Authorization' => ['Bearer token'],
        ]]);

        $this->assertEquals('[Filtered]', $result['Authorization']);
    }

    public function test_filter_headers_joins_array_values(): void
    {
        $fizwatch = new FizWatch(
            url: 'https://fizwatch.test',
            key: 'fiz_abc123',
            sensitiveHeaders: [],
        );

        $result = $this->callPrivateMethod($fizwatch, 'filterHeaders', [[
            'accept' => ['text/html', 'application/json'],
        ]]);

        $this->assertEquals('text/html, application/json', $result['accept']);
    }
}
