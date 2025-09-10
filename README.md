# monolog-openobserve

Monolog handler for [OpenObserve](https://openobserve.ai/)

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
