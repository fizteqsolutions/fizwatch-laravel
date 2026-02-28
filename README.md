# FizWatch Laravel

Laravel client package for [FizWatch](https://github.com/fizteqsolutions/fizwatch) error tracking. Captures exceptions and sends them to your FizWatch instance automatically.

Supports Laravel 8 through 12.

## Installation

```bash
composer require fizteqsolutions/fizwatch-laravel
```

The service provider is auto-discovered — no manual registration needed.

## Configuration

Add these two variables to your `.env` file:

```env
FIZWATCH_URL=https://your-fizwatch-instance.com
FIZWATCH_KEY=fiz_your_project_api_key
```

Both are required. If either is missing, the package does nothing — no HTTP calls, no logs, no interference with your application.

### Publishing the config (optional)

```bash
php artisan vendor:publish --tag=fizwatch-config
```

This publishes `config/fizwatch.php` where you can customize:

- **`timeout`** — HTTP timeout in seconds (default: 5)
- **`sensitive_fields`** — Request body/query fields replaced with `[Filtered]` before sending (default: `_token`, `password`, `password_confirmation`, `credit_card`, `ssn`, `secret`)
- **`sensitive_headers`** — HTTP headers replaced with `[Filtered]` (default: `authorization`, `cookie`, `set-cookie`)
- **`ignored_exceptions`** — Exception classes that should not be reported to FizWatch (default: none)

### Filtering exceptions

If certain exceptions are too noisy (e.g., OAuth errors from mobile apps), you can ignore them entirely. First publish the config, then add exception classes to the `ignored_exceptions` array:

```php
// config/fizwatch.php

'ignored_exceptions' => [
    \League\OAuth2\Server\Exception\OAuthServerException::class,
    \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
],
```

Uses `instanceof` matching — adding a parent class also ignores all of its subclasses.

## Testing your integration

```bash
php artisan fizwatch:test
```

This sends a test exception to your FizWatch instance. You should see it appear on your dashboard immediately.

## How it works

The package hooks into Laravel's exception handler via `reportable()`. When an exception occurs:

1. If `FIZWATCH_URL` or `FIZWATCH_KEY` is not set — does nothing
2. Builds a payload with the exception details, stacktrace, environment info, and HTTP request data
3. Sends it to the FizWatch API
4. If anything fails — fails silently, no logs, no side effects

Normal Laravel error logging is never affected. The package works alongside other error tracking tools (like Sentry) without conflict.

## What gets sent

Each error report includes:

- **Exception** — class, message, file, line
- **Stacktrace** — up to 50 frames
- **Environment** — PHP version, server OS, Laravel environment
- **HTTP Request** (when available) — method, URL, headers, body, query params (with sensitive fields filtered)
