# monolog-openobserve

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

## Handling failures

By default the handler throws when OpenObserve cannot be reached or rejects the
request, including on an authentication failure such as `401`. Set
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

Swallowed failures disappear silently. To keep a record of them, pass a
`fallbackLogger` — any PSR-3 logger — and every ignored failure is reported to it
at error level, with the original exception under the `exception` context key:

```php
use Monolog\Handler\ErrorLogHandler;

$handler = new OpenObserveHandler(
	host: 'http://localhost:5080',
	organizationId: 'default',
	streamName: 'monolog',
	username: 'admin@yourdomain.com',
	password: 'yourpassword',
	ignoreFailure: true,
	fallbackLogger: new Logger('openobserve', [new ErrorLogHandler()]),
);
```

The fallback logger must not write back to this handler, as that would recurse.
It is only consulted while `ignoreFailure` is on; when the handler rethrows, the
caller already sees the exception.
