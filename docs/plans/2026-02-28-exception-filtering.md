# Exception Filtering Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Allow users to configure a list of exception classes that FizWatch should silently ignore, reducing noise from known/expected errors.

**Architecture:** Add an `ignored_exceptions` config array. The `FizWatch` class checks each exception against this list using `instanceof` (supporting subclass matching) at the top of `captureException()`. The `sendTest()` method bypasses this check so `fizwatch:test` always works.

**Tech Stack:** PHP 8.0+, Laravel 8-12

**Design doc:** `docs/plans/2026-02-28-exception-filtering-design.md`

---

### Task 1: Add `ignored_exceptions` config key

**Files:**
- Modify: `config/fizwatch.php:72` (after sensitive_headers block)

**Step 1: Add the config block**

Add after line 72 (closing `],` of `sensitive_headers`):

```php

    /*
    |--------------------------------------------------------------------------
    | Ignored Exceptions
    |--------------------------------------------------------------------------
    |
    | Exception classes listed here will not be reported to FizWatch.
    | Uses instanceof matching, so adding a parent class also ignores
    | all of its subclasses.
    |
    */

    'ignored_exceptions' => [
        // \League\OAuth2\Server\Exception\OAuthServerException::class,
        // \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
    ],
```

**Step 2: Commit**

```bash
git add config/fizwatch.php
git commit -m "Add ignored_exceptions config key"
```

---

### Task 2: Wire config into FizWatch constructor

**Files:**
- Modify: `src/FizWatch.php:13-19` (constructor)
- Modify: `src/FizWatchServiceProvider.php:14-22` (singleton binding)

**Step 1: Add `ignoredExceptions` constructor parameter**

In `src/FizWatch.php`, change the constructor (lines 13-19) to:

```php
    public function __construct(
        private ?string $url,
        private ?string $key,
        private int $timeout = 5,
        private array $sensitiveFields = [],
        private array $sensitiveHeaders = [],
        private array $ignoredExceptions = [],
    ) {}
```

**Step 2: Pass config value in service provider**

In `src/FizWatchServiceProvider.php`, change the singleton binding (lines 14-22) to:

```php
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
```

**Step 3: Commit**

```bash
git add src/FizWatch.php src/FizWatchServiceProvider.php
git commit -m "Wire ignored_exceptions config into FizWatch constructor"
```

---

### Task 3: Add `shouldIgnore()` method and call it in `captureException()`

**Files:**
- Modify: `src/FizWatch.php:29-41` (captureException method)

**Step 1: Add `shouldIgnore()` method**

Add after `isConfigured()` (after line 24):

```php

    /**
     * Check if the given exception should be ignored based on configured class list.
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
```

**Step 2: Call `shouldIgnore()` in `captureException()`**

Change `captureException()` (lines 29-41) to add the check after `isConfigured()`:

```php
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
```

Note: `sendTest()` does NOT call `shouldIgnore()`, so `fizwatch:test` always works regardless of the ignore list.

**Step 3: Commit**

```bash
git add src/FizWatch.php
git commit -m "Add shouldIgnore() and call it in captureException()"
```

---

### Task 4: Update README with Filtering Exceptions section

**Files:**
- Modify: `README.md`

**Step 1: Add filtering section**

Add after the "Publishing the config (optional)" section (after line 36) and before "## Testing your integration":

```markdown

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
```

**Step 2: Update the config options list**

On line 32-36, add `ignored_exceptions` to the list of customizable options:

```markdown
- **`timeout`** — HTTP timeout in seconds (default: 5)
- **`sensitive_fields`** — Request body/query fields replaced with `[Filtered]` before sending (default: `_token`, `password`, `password_confirmation`, `credit_card`, `ssn`, `secret`)
- **`sensitive_headers`** — HTTP headers replaced with `[Filtered]` (default: `authorization`, `cookie`, `set-cookie`)
- **`ignored_exceptions`** — Exception classes that should not be reported to FizWatch (default: none)
```

**Step 3: Commit**

```bash
git add README.md
git commit -m "Document exception filtering in README"
```

---

### Task 5: Manual verification

**Step 1: Review all changed files**

```bash
git log --oneline -5
git diff HEAD~4..HEAD
```

**Step 2: Verify the package loads in the FizWatch app**

From the FizWatch project directory, run:

```bash
ddev artisan fizwatch:test
```

This uses the locally mounted package and confirms the new constructor parameter doesn't break anything.
