
# monolog-openobserve

> Monolog handler for [OpenObserve](https://openobserve.ai/). Easily send your PHP logs to OpenObserve using Monolog.

## Features

- Monolog handler for sending logs to OpenObserve
- Custom JSON formatter with `_timestamp` field
- Supports batch logging

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
	stream: 'monolog',
	username: 'admin@yourdomain.com',
	password: 'yourpassword',
);

$logger = new Logger('app');
$logger->pushHandler($handler);

$logger->info('Hello OpenObserve!', ['foo' => 'bar']);
```

## Environment Variables for Testing

For running tests, set the following environment variables (see `phpunit.xml`):

- `O2_HOST`
- `O2_ORGANIZATION_ID`
- `O2_STREAM`
- `O2_USERNAME`
- `O2_PASSWORD`

## Testing

```bash
composer test
```

## Formatter

The included `OpenObserveFormatter` extends Monolog's `JsonFormatter` and adds a `_timestamp` field (UNIX timestamp) to each log record.

## License

MIT
