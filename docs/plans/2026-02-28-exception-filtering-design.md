# Exception Filtering Design

## Problem

FizWatch captures all exceptions, including noisy ones like `OAuthServerException` that flood the database on apps with mobile clients. Users need a way to skip certain exception classes entirely.

## Design

### Config

Add `ignored_exceptions` array to `config/fizwatch.php`. Empty by default. Users list fully-qualified class names:

```php
'ignored_exceptions' => [
    League\OAuth2\Server\Exception\OAuthServerException::class,
    Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
],
```

### Core Logic (FizWatch.php)

- Add `$ignoredExceptions` property, populated from config in constructor
- Add `shouldIgnore(Throwable $e): bool` — checks `$e instanceof $class` for each entry (supports subclass matching)
- Call at top of `captureException()` — if ignored, return early silently

### Flow

```
Exception → reportable() → captureException()
  → shouldIgnore()? → yes → return (skip)
                    → no  → build payload → send to API
```

### Test Command

`sendTest()` bypasses the ignore check so `fizwatch:test` always works, even if `RuntimeException` is in the ignored list.

### Documentation

Update README.md with a "Filtering Exceptions" section showing configuration and usage examples.

### Out of Scope

- No message-pattern matching
- No runtime API (`FizWatch::ignore()`)
- No env variable — class lists belong in config files
