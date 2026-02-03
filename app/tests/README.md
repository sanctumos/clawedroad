# PHP Test Suite

Unit, integration, and E2E tests for the Marketplace PHP application.

## Requirements

- PHP 8.0+
- Composer
- SQLite (used for test database)

## Install

```bash
cd app
composer install
```

## Run All Tests

```bash
./vendor/bin/phpunit
```

## Run by Suite

```bash
# Unit tests only
./vendor/bin/phpunit --testsuite Unit

# Integration tests only
./vendor/bin/phpunit --testsuite Integration

# E2E tests only
./vendor/bin/phpunit --testsuite E2E
```

## Run with Coverage

For code coverage you need **PCOV** or **Xdebug**:

```bash
# With PCOV (recommended)
php -d pcov.enabled=1 ./vendor/bin/phpunit --coverage-text

# Or with Xdebug
php -d xdebug.mode=coverage ./vendor/bin/phpunit --coverage-text
```

**Windows (PCOV not in PHP):** If you dropped PCOV into `app/pcov_ext/` (e.g. from [PECL pcov Windows](https://pecl.php.net/package/pcov)), run:

```bash
php -d extension=pcov_ext/php_pcov.dll -d pcov.enabled=1 -d pcov.directory=. vendor/bin/phpunit --coverage-text
```

HTML report is written to `build/coverage/`.

### Current coverage (Unit + Integration + E2E)

| Metric  | Value   |
|---------|---------|
| Lines   | ~66%    |
| Methods | ~58%    |
| Classes | 2/13 fully covered (ApiKey, StatusMachine) |

Coverage is scoped to `public/includes` (shared library code). Entrypoint scripts (`public/*.php`, `public/api/*.php`) are not included in the report.

## Structure

- **tests/Unit/** – Unit tests for classes (Env, Db, User, Session, ApiKey, Config, StatusMachine, bootstrap, api_helpers)
- **tests/Integration/** – Integration tests (Schema, Views, Config with real DB)
- **tests/E2E/** – End-to-end tests (HTTP request simulation via run_request.php)
- **tests/bootstrap.php** – Sets test .env, loads app, runs schema
- **tests/run_request.php** – E2E helper: runs an endpoint script with given request, writes response to file

## Test Database

Tests use `app/db/test.sqlite`. The bootstrap overwrites `app/.env` with test config for the run and restores it on shutdown.

## E2E How It Works

E2E tests pass request params (method, URI, GET, POST, headers) to `run_request.php` via a temp file. The runner sets `$_SERVER`, `$_GET`, `$_POST`, then requires the endpoint script. Response code and body are captured in a shutdown handler and written to a file, which the test reads and asserts on.
