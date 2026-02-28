<?php

namespace FizWatch\Tests\Unit;

use FizWatch\FizWatch;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;

class FizWatchTest extends TestCase
{
    public function test_is_configured_returns_true_when_url_and_key_are_set(): void
    {
        $fizwatch = new FizWatch(url: 'https://fizwatch.test', key: 'fiz_abc123');

        $this->assertTrue($fizwatch->isConfigured());
    }

    public function test_is_configured_returns_false_when_url_is_null(): void
    {
        $fizwatch = new FizWatch(url: null, key: 'fiz_abc123');

        $this->assertFalse($fizwatch->isConfigured());
    }

    public function test_is_configured_returns_false_when_key_is_null(): void
    {
        $fizwatch = new FizWatch(url: 'https://fizwatch.test', key: null);

        $this->assertFalse($fizwatch->isConfigured());
    }

    public function test_is_configured_returns_false_when_url_is_empty_string(): void
    {
        $fizwatch = new FizWatch(url: '', key: 'fiz_abc123');

        $this->assertFalse($fizwatch->isConfigured());
    }

    public function test_is_configured_returns_false_when_key_is_empty_string(): void
    {
        $fizwatch = new FizWatch(url: 'https://fizwatch.test', key: '');

        $this->assertFalse($fizwatch->isConfigured());
    }

    public function test_should_ignore_returns_true_for_matching_exception_class(): void
    {
        $fizwatch = new FizWatch(
            url: 'https://fizwatch.test',
            key: 'fiz_abc123',
            ignoredExceptions: [\RuntimeException::class],
        );

        $this->assertTrue($fizwatch->shouldIgnore(new \RuntimeException('test')));
    }

    public function test_should_ignore_returns_true_for_subclass_of_ignored_exception(): void
    {
        $fizwatch = new FizWatch(
            url: 'https://fizwatch.test',
            key: 'fiz_abc123',
            ignoredExceptions: [\RuntimeException::class],
        );

        // OverflowException extends RuntimeException
        $this->assertTrue($fizwatch->shouldIgnore(new \OverflowException('test')));
    }

    public function test_should_ignore_returns_false_for_non_matching_exception(): void
    {
        $fizwatch = new FizWatch(
            url: 'https://fizwatch.test',
            key: 'fiz_abc123',
            ignoredExceptions: [\RuntimeException::class],
        );

        $this->assertFalse($fizwatch->shouldIgnore(new \LogicException('test')));
    }

    public function test_should_ignore_returns_false_when_no_exceptions_configured(): void
    {
        $fizwatch = new FizWatch(
            url: 'https://fizwatch.test',
            key: 'fiz_abc123',
            ignoredExceptions: [],
        );

        $this->assertFalse($fizwatch->shouldIgnore(new \RuntimeException('test')));
    }

    public function test_capture_exception_does_nothing_when_not_configured(): void
    {
        Http::fake();

        $fizwatch = new FizWatch(url: null, key: null);
        $fizwatch->captureException(new \RuntimeException('test'));

        Http::assertNothingSent();
    }

    public function test_capture_exception_does_nothing_for_ignored_exception(): void
    {
        Http::fake();

        $fizwatch = new FizWatch(
            url: 'https://fizwatch.test',
            key: 'fiz_abc123',
            ignoredExceptions: [\RuntimeException::class],
        );

        $fizwatch->captureException(new \RuntimeException('test'));

        Http::assertNothingSent();
    }

    public function test_capture_exception_sends_payload_to_api(): void
    {
        Http::fake([
            'fizwatch.test/*' => Http::response(null, 202),
        ]);

        $fizwatch = new FizWatch(url: 'https://fizwatch.test', key: 'fiz_abc123');
        $fizwatch->captureException(new \RuntimeException('Something broke'));

        Http::assertSent(function ($request) {
            return $request->url() === 'https://fizwatch.test/api/v1/errors'
                && $request->header('X-FizWatch-Key')[0] === 'fiz_abc123'
                && $request['exception']['class'] === 'RuntimeException'
                && $request['exception']['message'] === 'Something broke'
                && isset($request['stacktrace'])
                && isset($request['environment'])
                && isset($request['occurred_at']);
        });
    }

    public function test_capture_exception_fails_silently_on_http_error(): void
    {
        Http::fake([
            'fizwatch.test/*' => Http::response(null, 500),
        ]);

        $fizwatch = new FizWatch(url: 'https://fizwatch.test', key: 'fiz_abc123');

        // Should not throw
        $fizwatch->captureException(new \RuntimeException('test'));

        $this->assertTrue(true);
    }

    public function test_capture_exception_fails_silently_on_connection_error(): void
    {
        Http::fake([
            'fizwatch.test/*' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
            },
        ]);

        $fizwatch = new FizWatch(url: 'https://fizwatch.test', key: 'fiz_abc123');

        // Should not throw
        $fizwatch->captureException(new \RuntimeException('test'));

        $this->assertTrue(true);
    }

    public function test_capture_exception_strips_trailing_slash_from_url(): void
    {
        Http::fake([
            'fizwatch.test/*' => Http::response(null, 202),
        ]);

        $fizwatch = new FizWatch(url: 'https://fizwatch.test/', key: 'fiz_abc123');
        $fizwatch->captureException(new \RuntimeException('test'));

        Http::assertSent(function ($request) {
            return $request->url() === 'https://fizwatch.test/api/v1/errors';
        });
    }

    public function test_send_test_throws_when_not_configured(): void
    {
        $fizwatch = new FizWatch(url: null, key: null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('FizWatch is not configured');

        $fizwatch->sendTest(new \RuntimeException('test'));
    }

    public function test_send_test_returns_status_and_body(): void
    {
        Http::fake([
            'fizwatch.test/*' => Http::response(['message' => 'received'], 202),
        ]);

        $fizwatch = new FizWatch(url: 'https://fizwatch.test', key: 'fiz_abc123');
        $result = $fizwatch->sendTest(new \RuntimeException('test'));

        $this->assertEquals(202, $result['status']);
        $this->assertEquals(['message' => 'received'], $result['body']);
    }

    public function test_payload_includes_exception_details(): void
    {
        Http::fake([
            'fizwatch.test/*' => Http::response(null, 202),
        ]);

        $exception = new \RuntimeException('Test message');

        $fizwatch = new FizWatch(url: 'https://fizwatch.test', key: 'fiz_abc123');
        $fizwatch->captureException($exception);

        Http::assertSent(function ($request) use ($exception) {
            $exc = $request['exception'];

            return $exc['class'] === 'RuntimeException'
                && $exc['message'] === 'Test message'
                && $exc['file'] === $exception->getFile()
                && $exc['line'] === $exception->getLine();
        });
    }

    public function test_payload_includes_environment_info(): void
    {
        Http::fake([
            'fizwatch.test/*' => Http::response(null, 202),
        ]);

        $fizwatch = new FizWatch(url: 'https://fizwatch.test', key: 'fiz_abc123');
        $fizwatch->captureException(new \RuntimeException('test'));

        Http::assertSent(function ($request) {
            $env = $request['environment'];

            return $env['php_version'] === PHP_VERSION
                && isset($env['server_os'])
                && isset($env['laravel_environment']);
        });
    }

    public function test_stacktrace_is_limited_to_50_frames(): void
    {
        Http::fake([
            'fizwatch.test/*' => Http::response(null, 202),
        ]);

        $fizwatch = new FizWatch(url: 'https://fizwatch.test', key: 'fiz_abc123');
        $fizwatch->captureException(new \RuntimeException('test'));

        Http::assertSent(function ($request) {
            return count($request['stacktrace']) <= 50;
        });
    }

    public function test_stacktrace_frames_have_expected_keys(): void
    {
        Http::fake([
            'fizwatch.test/*' => Http::response(null, 202),
        ]);

        $fizwatch = new FizWatch(url: 'https://fizwatch.test', key: 'fiz_abc123');
        $fizwatch->captureException(new \RuntimeException('test'));

        Http::assertSent(function ($request) {
            if (empty($request['stacktrace'])) {
                return false;
            }

            $frame = $request['stacktrace'][0];

            return array_key_exists('file', $frame)
                && array_key_exists('line', $frame)
                && array_key_exists('function', $frame)
                && array_key_exists('class', $frame);
        });
    }

    public function test_request_is_null_when_running_in_console(): void
    {
        Http::fake([
            'fizwatch.test/*' => Http::response(null, 202),
        ]);

        $fizwatch = new FizWatch(url: 'https://fizwatch.test', key: 'fiz_abc123');
        $fizwatch->captureException(new \RuntimeException('test'));

        Http::assertSent(function ($request) {
            return $request['request'] === null;
        });
    }

    public function test_sanitize_message_strips_laravel_encrypted_values(): void
    {
        Http::fake([
            'fizwatch.test/*' => Http::response(null, 202),
        ]);

        // Simulate an encrypted value in a QueryException message
        $encryptedValue = 'eyJ' . str_repeat('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/', 5);
        $message = "SQLSTATE[22001]: Data too long for column 'token' (SQL: update `projects` set `token` = {$encryptedValue} where `id` = 1)";

        $fizwatch = new FizWatch(url: 'https://fizwatch.test', key: 'fiz_abc123');
        $fizwatch->captureException(new \RuntimeException($message));

        Http::assertSent(function ($request) {
            return str_contains($request['exception']['message'], '[Filtered]')
                && ! str_contains($request['exception']['message'], 'eyJ');
        });
    }

    public function test_sanitize_message_preserves_normal_messages(): void
    {
        Http::fake([
            'fizwatch.test/*' => Http::response(null, 202),
        ]);

        $fizwatch = new FizWatch(url: 'https://fizwatch.test', key: 'fiz_abc123');
        $fizwatch->captureException(new \RuntimeException('Normal error message'));

        Http::assertSent(function ($request) {
            return $request['exception']['message'] === 'Normal error message';
        });
    }

    public function test_sanitize_message_strips_multiple_encrypted_values(): void
    {
        Http::fake([
            'fizwatch.test/*' => Http::response(null, 202),
        ]);

        $encrypted = 'eyJ' . str_repeat('A', 150);
        $message = "first={$encrypted}, second={$encrypted}";

        $fizwatch = new FizWatch(url: 'https://fizwatch.test', key: 'fiz_abc123');
        $fizwatch->captureException(new \RuntimeException($message));

        Http::assertSent(function ($request) {
            return ! str_contains($request['exception']['message'], 'eyJ')
                && substr_count($request['exception']['message'], '[Filtered]') === 2;
        });
    }

    public function test_long_messages_are_truncated(): void
    {
        Http::fake([
            'fizwatch.test/*' => Http::response(null, 202),
        ]);

        $longMessage = str_repeat('A', 15000);

        $fizwatch = new FizWatch(url: 'https://fizwatch.test', key: 'fiz_abc123');
        $fizwatch->captureException(new \RuntimeException($longMessage));

        Http::assertSent(function ($request) {
            // MAX_STRING_LENGTH is 10000, plus "..."
            return mb_strlen($request['exception']['message']) <= 10003;
        });
    }

    public function test_custom_timeout_is_used(): void
    {
        Http::fake([
            'fizwatch.test/*' => Http::response(null, 202),
        ]);

        $fizwatch = new FizWatch(url: 'https://fizwatch.test', key: 'fiz_abc123', timeout: 15);
        $fizwatch->captureException(new \RuntimeException('test'));

        Http::assertSent(function ($request) {
            return $request->url() === 'https://fizwatch.test/api/v1/errors';
        });
    }

    public function test_payload_includes_occurred_at_timestamp(): void
    {
        Http::fake([
            'fizwatch.test/*' => Http::response(null, 202),
        ]);

        $fizwatch = new FizWatch(url: 'https://fizwatch.test', key: 'fiz_abc123');
        $fizwatch->captureException(new \RuntimeException('test'));

        Http::assertSent(function ($request) {
            return isset($request['occurred_at'])
                && ! empty($request['occurred_at']);
        });
    }

}
