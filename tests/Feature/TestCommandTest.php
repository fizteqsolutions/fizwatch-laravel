<?php

namespace FizWatch\Tests\Feature;

use FizWatch\FizWatch;
use FizWatch\FizWatchServiceProvider;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;

class TestCommandTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [FizWatchServiceProvider::class];
    }

    public function test_command_fails_when_not_configured(): void
    {
        $this->artisan('fizwatch:test')
            ->expectsOutputToContain('FizWatch is not configured')
            ->assertExitCode(1);
    }

    public function test_command_succeeds_when_api_returns_202(): void
    {
        Http::fake([
            'fizwatch.test/*' => Http::response(['message' => 'received'], 202),
        ]);

        config([
            'fizwatch.url' => 'https://fizwatch.test',
            'fizwatch.key' => 'fiz_abc123',
        ]);

        // Re-bind FizWatch with new config
        $this->app->singleton(FizWatch::class, function ($app) {
            return new FizWatch(
                url: $app['config']->get('fizwatch.url'),
                key: $app['config']->get('fizwatch.key'),
                timeout: (int) $app['config']->get('fizwatch.timeout', 5),
                sensitiveFields: $app['config']->get('fizwatch.sensitive_fields', []),
                sensitiveHeaders: $app['config']->get('fizwatch.sensitive_headers', []),
                ignoredExceptions: $app['config']->get('fizwatch.ignored_exceptions', []),
            );
        });

        $this->artisan('fizwatch:test')
            ->expectsOutputToContain('Test exception sent successfully')
            ->assertExitCode(0);
    }

    public function test_command_fails_when_api_returns_error(): void
    {
        Http::fake([
            'fizwatch.test/*' => Http::response(['error' => 'unauthorized'], 401),
        ]);

        config([
            'fizwatch.url' => 'https://fizwatch.test',
            'fizwatch.key' => 'invalid_key',
        ]);

        $this->app->singleton(FizWatch::class, function ($app) {
            return new FizWatch(
                url: $app['config']->get('fizwatch.url'),
                key: $app['config']->get('fizwatch.key'),
                timeout: (int) $app['config']->get('fizwatch.timeout', 5),
                sensitiveFields: $app['config']->get('fizwatch.sensitive_fields', []),
                sensitiveHeaders: $app['config']->get('fizwatch.sensitive_headers', []),
                ignoredExceptions: $app['config']->get('fizwatch.ignored_exceptions', []),
            );
        });

        $this->artisan('fizwatch:test')
            ->expectsOutputToContain('FizWatch responded with HTTP 401')
            ->assertExitCode(1);
    }

    public function test_command_fails_on_connection_error(): void
    {
        Http::fake([
            '*' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
            },
        ]);

        config([
            'fizwatch.url' => 'https://fizwatch.test',
            'fizwatch.key' => 'fiz_abc123',
        ]);

        $this->app->singleton(FizWatch::class, function ($app) {
            return new FizWatch(
                url: $app['config']->get('fizwatch.url'),
                key: $app['config']->get('fizwatch.key'),
                timeout: (int) $app['config']->get('fizwatch.timeout', 5),
                sensitiveFields: $app['config']->get('fizwatch.sensitive_fields', []),
                sensitiveHeaders: $app['config']->get('fizwatch.sensitive_headers', []),
                ignoredExceptions: $app['config']->get('fizwatch.ignored_exceptions', []),
            );
        });

        $this->artisan('fizwatch:test')
            ->expectsOutputToContain('Failed to send test exception')
            ->assertExitCode(1);
    }
}
