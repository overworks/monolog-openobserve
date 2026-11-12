# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/). While the package is
at 0.x, a minor release (0.4 to 0.5) may contain breaking changes and a patch
release does not.

## [0.5.0] - 2026-11-12

Undoes the constructor change made in 0.4.0 and reworks failure handling.

### Changed

- **Breaking:** The constructor takes `username` and `password` again instead of
  `token`, as it did up to 0.3.
- **Breaking:** `ignoreFailure` accepts a closure as well as a bool, replacing
  `fallbackLogger`. The closure is called instead of throwing, with the exception
  and the list of `LogRecord` objects that could not be sent.

  | `ignoreFailure` | When sending fails |
  | --- | --- |
  | `false` (default) | the exception is thrown |
  | `true` | the exception is swallowed |
  | `Closure` | the closure is called instead of throwing |

- **Breaking:** The protected `send(string $payload)` method is now
  `send(string $payload, array $records)`.
- `OpenObserveHandlerBuilder::withIgnoreFailure()` accepts a closure too.

### Removed

- **Breaking:** The `token` constructor argument and
  `OpenObserveHandlerBuilder::withToken()`.
- **Breaking:** The `fallbackLogger` constructor argument and
  `OpenObserveHandlerBuilder::withFallbackLogger()`.
- The direct requirement on `psr/log`. It is still installed through Monolog.

### Upgrading from 0.4

The constructor signature changed from

```php
new OpenObserveHandler(
    $host, $organizationId, $streamName,
    $token,
    $ignoreFailure, $level, $bubble,
    $fallbackLogger,
    $connectTimeout, $timeout,
);
```

to

```php
new OpenObserveHandler(
    $host, $organizationId, $streamName,
    $username, $password,
    $ignoreFailure, $level, $bubble,
    $connectTimeout, $timeout,
);
```

**Authentication.** Pass the username and password instead of the token. Code
that already used `withCredentials()` needs no change.

```php
// 0.4
new OpenObserveHandler($host, $organizationId, $streamName, token: $token);
OpenObserveHandler::builder($host, $organizationId, $streamName)->withToken($token)->build();

// 0.5
new OpenObserveHandler($host, $organizationId, $streamName, username: $username, password: $password);
OpenObserveHandler::builder($host, $organizationId, $streamName)->withCredentials($username, $password)->build();
```

The token was the base64 encoding of `username:password`, so the two values can
be recovered from it if they are not at hand:

```php
[$username, $password] = explode(':', base64_decode($token), 2);
```

**Failure reporting.** Replace `fallbackLogger` with a closure. This keeps the
behaviour it had:

```php
// 0.4
new OpenObserveHandler(/* ... */ ignoreFailure: true, fallbackLogger: $logger);

// 0.5
new OpenObserveHandler(
    /* ... */
    ignoreFailure: fn (\Throwable $e) => $logger->error(
        'Failed to send log to OpenObserve: ' . $e->getMessage(),
        ['exception' => $e]
    ),
);
```

Because the records are passed too, they can be handed to another handler
rather than only reported:

```php
ignoreFailure: fn (\Throwable $e, array $records) => $fileHandler->handleBatch($records),
```

An exception thrown from the closure propagates to the caller, so it can rethrow
selectively. `ignoreFailure: false` combined with a fallback logger used to
throw without reporting; that combination no longer exists, as a closure always
replaces the throw. `ignoreFailure: true` and `false` behave as before.

**Positional arguments.** Named arguments are unaffected apart from the removals
above. Positional calls need checking:

| Argument | 0.4 position | 0.5 position |
| --- | --- | --- |
| `ignoreFailure` | 5 | 6 |
| `level` | 6 | 7 |
| `bubble` | 7 | 8 |
| `fallbackLogger` | 8 | removed |
| `connectTimeout` | 9 | 9 |
| `timeout` | 10 | 10 |

**Subclasses.** Besides the `send()` signature, the `$token` and
`$fallbackLogger` properties are removed, `$username` and `$password` are back,
and `$ignoreFailure` is typed `bool|\Closure`.

### Upgrading from 0.3

The constructor's authentication arguments are the same in 0.3 and 0.5, so only
the failure-handling changes apply:

- Replace `fallbackLogger` with a closure passed as `ignoreFailure`, as shown
  above.
- In positional calls, `connectTimeout` and `timeout` move from positions 10 and
  11 to 9 and 10, because `fallbackLogger` (position 9) is gone.
- Subclasses overriding `send()` must accept the new `array $records` argument.

`OpenObserveHandler::builder()`, added in 0.4.0, is available as an optional
alternative to the constructor.

## [0.4.0] - 2026-10-13

### Added

- `OpenObserveHandler::builder()` and `OpenObserveHandlerBuilder`, offering the
  constructor's options as fluent methods.
- Authentication with an ingestion token. Removed again in 0.5.0.

### Changed

- **Breaking:** The constructor's `username` and `password` arguments were
  replaced by a single `token` argument. Reverted in 0.5.0.

## [0.3.2] - 2026-09-29

### Fixed

- A batch filtered by level is sent as a JSON array rather than an object.
- A response that reports rejected records in `status[].failed` is treated as a
  failure even when its HTTP status is a success.
- A timed-out request is no longer retried, so retries cannot multiply the
  configured timeout or duplicate records.

## [0.3.1] - 2026-08-25

### Fixed

- `_timestamp` is sent in microseconds, as OpenObserve expects. Also released as
  0.2.1 and 0.1.5.

## [0.3.0] - 2026-08-03

### Added

- `connectTimeout` and `timeout` constructor arguments, defaulting to 2 and 5
  seconds.

## [0.2.1] - 2026-08-25

### Fixed

- `_timestamp` is sent in microseconds.

## [0.2.0] - 2026-07-21

### Added

- `fallbackLogger` constructor argument, reporting failures that `ignoreFailure`
  swallows.

### Changed

- **Breaking:** A non-2xx response from OpenObserve is treated as a failure and
  throws unless `ignoreFailure` is set.
- **Breaking:** PHP 8.3 or later is required.

## [0.1.5] - 2026-08-25

### Fixed

- `_timestamp` is sent in microseconds.

## [0.1.4] - 2025-12-04

### Fixed

- `ignoreFailure` also suppresses exceptions raised while sending.

## [0.1.3] - 2025-11-12

### Changed

- Requests are sent with the curl extension.

## [0.1.2] - 2025-09-16

### Changed

- `OpenObserveFormatter` extends `NormalizerFormatter` instead of
  `JsonFormatter`.

### Removed

- The `guzzlehttp/guzzle` dependency.

## [0.1.1] - 2025-09-16

### Changed

- The handler's `stream` constructor argument is renamed `streamName`.

## [0.1.0] - 2025-09-10

Initial release.

[0.5.0]: https://github.com/overworks/monolog-openobserve/compare/0.4.0...0.5.0
[0.4.0]: https://github.com/overworks/monolog-openobserve/compare/0.3.2...0.4.0
[0.3.2]: https://github.com/overworks/monolog-openobserve/compare/0.3.1...0.3.2
[0.3.1]: https://github.com/overworks/monolog-openobserve/compare/0.3.0...0.3.1
[0.3.0]: https://github.com/overworks/monolog-openobserve/compare/0.2.0...0.3.0
[0.2.1]: https://github.com/overworks/monolog-openobserve/compare/0.2.0...0.2.1
[0.2.0]: https://github.com/overworks/monolog-openobserve/compare/0.1.4...0.2.0
[0.1.5]: https://github.com/overworks/monolog-openobserve/compare/0.1.4...0.1.5
[0.1.4]: https://github.com/overworks/monolog-openobserve/compare/0.1.3...0.1.4
[0.1.3]: https://github.com/overworks/monolog-openobserve/compare/0.1.2...0.1.3
[0.1.2]: https://github.com/overworks/monolog-openobserve/compare/0.1.1...0.1.2
[0.1.1]: https://github.com/overworks/monolog-openobserve/compare/0.1.0...0.1.1
[0.1.0]: https://github.com/overworks/monolog-openobserve/releases/tag/0.1.0
