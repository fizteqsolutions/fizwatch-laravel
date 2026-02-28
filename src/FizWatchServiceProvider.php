<?php

namespace FizWatch;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\ServiceProvider;

class FizWatchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/fizwatch.php', 'fizwatch');

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
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/fizwatch.php' => config_path('fizwatch.php'),
            ], 'fizwatch-config');

            $this->commands([
                Commands\TestCommand::class,
            ]);
        }

        $this->registerExceptionHandler();
    }

    private function registerExceptionHandler(): void
    {
        try {
            $handler = $this->app->make(ExceptionHandler::class);

            if (method_exists($handler, 'reportable')) {
                $handler->reportable(function (\Throwable $e) {
                    $this->app->make(FizWatch::class)->captureException($e);
                });
            }
        } catch (\Throwable $ignored) {
            // If we can't register, fail silently.
        }
    }
}
