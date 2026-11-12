# monolog-openobserve

[![CI](https://github.com/overworks/monolog-openobserve/actions/workflows/php.yml/badge.svg?branch=0.x)](https://github.com/overworks/monolog-openobserve/actions/workflows/php.yml)
[![Latest Version](https://img.shields.io/packagist/v/minhyung/monolog-openobserve.svg)](https://packagist.org/packages/minhyung/monolog-openobserve)
[![PHP Version](https://img.shields.io/packagist/php-v/minhyung/monolog-openobserve.svg)](https://packagist.org/packages/minhyung/monolog-openobserve)
[![Total Downloads](https://img.shields.io/packagist/dt/minhyung/monolog-openobserve.svg)](https://packagist.org/packages/minhyung/monolog-openobserve)
[![License](https://img.shields.io/packagist/l/minhyung/monolog-openobserve.svg)](LICENSE)

Monolog handler for [OpenObserve](https://openobserve.ai/)

## Requirements

- PHP 8.3 or later
- the `curl` extension
- Monolog 3

## Installation

```bash
composer require minhyung/monolog-openobserve
```

## Usage

```php
use Minhyung\Monolog\OpenObserveHandler;
use Monolog\Logger;

$handler = new OpenObserveHandler(
	host: 'http://localhost:5080',
	organizationId: 'default',
	streamName: 'monolog',
	username: 'admin@yourdomain.com',
	password: 'yourpassword',
);

$logger = new Logger('app');
$logger->pushHandler($handler);

$logger->info('Hello OpenObserve!', ['foo' => 'bar']);
```

### Builder

The builder offers the same options as fluent methods:

```php
$handler = OpenObserveHandler::builder('http://localhost:5080', 'default', 'monolog')
    ->withCredentials('admin@yourdomain.com', 'yourpassword')
    ->withLevel(\Monolog\Level::Info)
    ->withTimeout(5.0)
    ->build();
```

The builder is mutable: each `with...()` call returns the same builder, and each
`build()` creates an independent handler. `withCredentials()` must be called
before building, otherwise `build()` throws `LogicException`.

All optional constructor arguments have matching builder methods:
`withIgnoreFailure()`, `withLevel()`, `withBubble()`, `withConnectTimeout()`, and
`withTimeout()`. Defaults are identical to the constructor: failures throw, level
is Debug, bubbling is enabled, and connection and request timeouts are 2 and 5
seconds.

**Upgrading from 0.4.0:** That release replaced the constructor's `username` and
`password` arguments with a single `token` argument and added `withToken()` to
the builder. Both have been removed, so constructor calls written for 0.3.x work
again. Code that passed a token should pass the username and password instead;
an OpenObserve ingestion token is the base64 encoding of `username:password`.

The `fallbackLogger` argument and `withFallbackLogger()` have been removed in
favour of passing a closure as `ignoreFailure`, described under
[Handling failures](#handling-failures). Replace `ignoreFailure: true,
fallbackLogger: $logger` with:

```php
ignoreFailure: fn (\Throwable $e) => $logger->error($e->getMessage(), ['exception' => $e]),
```

Positional callers should note that `connectTimeout` and `timeout` moved up one
position, to ninth and tenth.

## Handling failures

By default the handler throws when OpenObserve cannot be reached or rejects the
request, including on an authentication failure such as `401` or when an HTTP
success response reports rejected records in `status[].failed`. Set
`ignoreFailure: true` to swallow those errors so that a logging problem never
breaks the application:

```php
$handler = new OpenObserveHandler(
	host: 'http://localhost:5080',
	organizationId: 'default',
	streamName: 'monolog',
	username: 'admin@yourdomain.com',
	password: 'yourpassword',
	ignoreFailure: true,
);
```

Swallowed failures disappear silently. To act on them instead, pass a closure as
`ignoreFailure`. It is called in place of throwing, with the exception and the
log records that could not be sent:

```php
use Monolog\LogRecord;

$handler = new OpenObserveHandler(
	host: 'http://localhost:5080',
	organizationId: 'default',
	streamName: 'monolog',
	username: 'admin@yourdomain.com',
	password: 'yourpassword',
	ignoreFailure: function (\Throwable $e, array $records): void {
		error_log('OpenObserve logging failed: ' . $e->getMessage());
	},
);
```

`$records` is always a list of `LogRecord` objects — one for a single log call,
or every record of a failed batch — so it can be handed straight to another
handler to keep the logs that OpenObserve did not receive:

```php
use Monolog\Handler\StreamHandler;

$file = new StreamHandler('/var/log/app/openobserve-failed.log');

$handler = new OpenObserveHandler(
	host: 'http://localhost:5080',
	organizationId: 'default',
	streamName: 'monolog',
	username: 'admin@yourdomain.com',
	password: 'yourpassword',
	ignoreFailure: fn (\Throwable $e, array $records) => $file->handleBatch($records),
);
```

The closure must not log back to this handler, as that would recurse. An
exception thrown from the closure propagates to the caller, so it can also
rethrow selectively.

## Timeouts

Because a stalled or unreachable OpenObserve would otherwise block the
application on every log call, the request is bounded by two timeouts: 2 seconds
to connect and 5 seconds for the whole request. Adjust them — or set the request
timeout to `0` to disable it — via the `connectTimeout` and `timeout` arguments:

```php
$handler = new OpenObserveHandler(
	host: 'http://localhost:5080',
	organizationId: 'default',
	streamName: 'monolog',
	username: 'admin@yourdomain.com',
	password: 'yourpassword',
	connectTimeout: 2.0,
	timeout: 5.0,
);
```

Each log or batch is sent once, without automatic retries, so retries do not
multiply the configured timeout. A timeout does not guarantee that the server
has not already ingested the records; retrying may create duplicates.
