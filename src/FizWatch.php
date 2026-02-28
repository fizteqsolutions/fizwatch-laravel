<?php

namespace FizWatch;

use Illuminate\Support\Facades\Http;

class FizWatch
{
    private const MAX_STACKTRACE_FRAMES = 50;
    private const MAX_STRING_LENGTH = 10000;
    private const FILTERED_VALUE = '[Filtered]';

    public function __construct(
        private ?string $url,
        private ?string $key,
        private int $timeout = 5,
        private array $sensitiveFields = [],
        private array $sensitiveHeaders = [],
        private array $ignoredExceptions = [],
    ) {}

    public function isConfigured(): bool
    {
        return ! empty($this->url) && ! empty($this->key);
    }

    /**
     * Determine if the given exception should be ignored based on the configured list.
     */
    public function shouldIgnore(\Throwable $e): bool
    {
        foreach ($this->ignoredExceptions as $class) {
            if ($e instanceof $class) {
                return true;
            }
        }

        return false;
    }

    /**
     * Capture and send an exception to FizWatch. Fails completely silently.
     */
    public function captureException(\Throwable $e): void
    {
        try {
            if (! $this->isConfigured()) {
                return;
            }

            if ($this->shouldIgnore($e)) {
                return;
            }

            $payload = $this->buildPayload($e);
            $this->send($payload);
        } catch (\Throwable $ignored) {
            // Fail completely silently — no logs, no exceptions, no side effects.
        }
    }

    /**
     * Send a test exception. Unlike captureException, this throws on failure
     * so the test command can display meaningful error messages.
     *
     * @return array{status: int, body: mixed}
     */
    public function sendTest(\Throwable $e): array
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException(
                'FizWatch is not configured. Set FIZWATCH_URL and FIZWATCH_KEY in your .env file.'
            );
        }

        $payload = $this->buildPayload($e);
        $response = $this->send($payload);

        return [
            'status' => $response->status(),
            'body' => $response->json() ?? $response->body(),
        ];
    }

    private function buildPayload(\Throwable $e): array
    {
        return [
            'exception' => [
                'class' => get_class($e),
                'message' => $this->truncate($e->getMessage()),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ],
            'stacktrace' => $this->buildStacktrace($e),
            'environment' => $this->buildEnvironment(),
            'request' => $this->buildRequest(),
            'occurred_at' => now()->toIso8601String(),
        ];
    }

    private function buildStacktrace(\Throwable $e): array
    {
        $frames = [];

        foreach (array_slice($e->getTrace(), 0, self::MAX_STACKTRACE_FRAMES) as $frame) {
            $frames[] = [
                'file' => $frame['file'] ?? null,
                'line' => $frame['line'] ?? null,
                'function' => $frame['function'] ?? null,
                'class' => $frame['class'] ?? null,
            ];
        }

        return $frames;
    }

    private function buildEnvironment(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'server_os' => PHP_OS . ' ' . php_uname('r'),
            'laravel_environment' => app()->environment(),
        ];
    }

    private function buildRequest(): ?array
    {
        try {
            if (app()->runningInConsole()) {
                return null;
            }

            $request = request();

            return [
                'method' => $request->method(),
                'url' => $this->truncate($request->fullUrl()),
                'headers' => $this->filterHeaders($request->headers->all()),
                'body' => $this->filterFields($request->all()),
                'query_params' => $this->filterFields($request->query()),
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function filterHeaders(array $headers): array
    {
        $filtered = [];

        foreach ($headers as $name => $values) {
            $lowered = strtolower($name);

            if (in_array($lowered, $this->sensitiveHeaders, true)) {
                $filtered[$name] = self::FILTERED_VALUE;
            } else {
                $filtered[$name] = is_array($values) ? implode(', ', $values) : $values;
            }
        }

        return $filtered;
    }

    private function filterFields(array $data): array
    {
        $filtered = [];
        $loweredSensitive = array_map('strtolower', $this->sensitiveFields);

        foreach ($data as $key => $value) {
            if (in_array(strtolower($key), $loweredSensitive, true)) {
                $filtered[$key] = self::FILTERED_VALUE;
            } elseif (is_array($value)) {
                $filtered[$key] = $this->filterFields($value);
            } else {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }

    /**
     * @return \Illuminate\Http\Client\Response
     */
    private function send(array $payload)
    {
        return Http::timeout($this->timeout)
            ->acceptJson()
            ->withHeaders([
                'X-FizWatch-Key' => $this->key,
            ])
            ->post(rtrim($this->url, '/') . '/api/v1/errors', $payload);
    }

    private function truncate(string $value, int $max = self::MAX_STRING_LENGTH): string
    {
        return mb_strlen($value) > $max
            ? mb_substr($value, 0, $max) . '...'
            : $value;
    }
}
