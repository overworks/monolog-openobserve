<?php

namespace Minhyung\Monolog\Tests;

use Minhyung\Monolog\OpenObserveFormatter;
use Minhyung\Monolog\OpenObserveHandler;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

class OpenObserveTest extends TestCase
{
    protected $faker;

    /** @var resource|null */
    protected static $server;

    protected static string $serverHost;

    protected function faker()
    {
        return $this->faker ??= \Faker\Factory::create();
    }

    /**
     * Boots the built-in PHP server that stands in for OpenObserve.
     */
    public static function setUpBeforeClass(): void
    {
        $port = self::findFreePort();
        self::$serverHost = "http://127.0.0.1:{$port}";

        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        self::$server = proc_open(
            [PHP_BINARY, '-S', "127.0.0.1:{$port}", __DIR__ . '/fixtures/router.php'],
            $descriptors,
            $pipes
        );

        if (! \is_resource(self::$server)) {
            self::fail('Could not start the stub OpenObserve server.');
        }

        // The server needs a moment before it accepts connections.
        for ($i = 0; $i < 100; $i++) {
            $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
            if ($socket !== false) {
                fclose($socket);
                return;
            }
            usleep(50_000);
        }

        self::fail('The stub OpenObserve server did not become ready.');
    }

    public static function tearDownAfterClass(): void
    {
        if (\is_resource(self::$server)) {
            proc_terminate(self::$server);
            proc_close(self::$server);
            self::$server = null;
        }
    }

    protected static function findFreePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($socket === false) {
            self::fail("Could not reserve a port: {$errstr}");
        }

        $name = stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr($name, strrpos($name, ':') + 1);
    }

    /**
     * Builds a handler pointed at the stub server, using the organization id to
     * select the status code the stub will respond with.
     */
    protected function handlerForStatus(int $status, bool $ignoreFailure = false): OpenObserveHandler
    {
        return new OpenObserveHandler(
            self::$serverHost,
            (string) $status,
            'stream',
            'user',
            'pass',
            $ignoreFailure
        );
    }

    public function testFormatter(): void
    {
        $formatter = new OpenObserveFormatter();

        $handler = new TestHandler();
        $handler->setFormatter($formatter);

        $message = $this->faker()->sentence();

        $log = new Logger('test');
        $log->pushHandler($handler);
        $log->info($message, ['foo' => 'bar']);

        $this->assertTrue($handler->hasInfoRecords());
        $records = $handler->getRecords();
        $this->assertCount(1, $records);
        $record = $records[0];
        $this->assertArrayHasKey('formatted', $record);
        $formatted = json_decode($record['formatted'], true);
        $this->assertArrayHasKey('_timestamp', $formatted);
        $this->assertEquals($record['datetime']->getTimestamp(), $formatted['_timestamp']);
        $this->assertArrayHasKey('message', $formatted);
        $this->assertEquals($message, $formatted['message']);
        $this->assertArrayHasKey('context', $formatted);
        $this->assertEquals('bar', $formatted['context']['foo']);
    }

    public function testSuccessfulResponseDoesNotThrow(): void
    {
        $log = new Logger('test');
        $log->pushHandler($this->handlerForStatus(200));
        $log->info($this->faker()->sentence());

        $this->assertTrue(true);
    }

    /**
     * A 401 is a successful request as far as curl is concerned, so the handler
     * has to inspect the status code to notice that the log was rejected.
     */
    public function testUnauthorizedResponseThrows(): void
    {
        $log = new Logger('test');
        $log->pushHandler($this->handlerForStatus(401));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/HTTP 401/');

        $log->info($this->faker()->sentence());
    }

    public function testUnauthorizedResponseIsSuppressedWhenIgnoringFailure(): void
    {
        $log = new Logger('test');
        $log->pushHandler($this->handlerForStatus(401, true));
        $log->info($this->faker()->sentence());

        $this->assertTrue(true);
    }

    public function testSwallowedFailureIsReportedToFallbackLogger(): void
    {
        $fallbackHandler = new TestHandler();
        $fallbackLogger = new Logger('fallback', [$fallbackHandler]);

        $handler = new OpenObserveHandler(
            self::$serverHost,
            '401',
            'stream',
            'user',
            'pass',
            true,
            Level::Debug,
            true,
            $fallbackLogger
        );

        $log = new Logger('test');
        $log->pushHandler($handler);
        $log->info($this->faker()->sentence());

        $this->assertTrue($fallbackHandler->hasErrorRecords());
        $records = $fallbackHandler->getRecords();
        $this->assertCount(1, $records);
        $this->assertStringContainsString('HTTP 401', $records[0]['message']);
        $this->assertInstanceOf(\RuntimeException::class, $records[0]['context']['exception']);
    }

    public function testFailureIsNotReportedToFallbackLoggerWhenRethrown(): void
    {
        $fallbackHandler = new TestHandler();
        $fallbackLogger = new Logger('fallback', [$fallbackHandler]);

        $handler = new OpenObserveHandler(
            self::$serverHost,
            '401',
            'stream',
            'user',
            'pass',
            false,
            Level::Debug,
            true,
            $fallbackLogger
        );

        $log = new Logger('test');
        $log->pushHandler($handler);

        try {
            $log->info($this->faker()->sentence());
            $this->fail('Expected the handler to rethrow.');
        } catch (\RuntimeException) {
            // The caller sees the exception, so reporting it again would double up.
        }

        $this->assertFalse($fallbackHandler->hasErrorRecords());
    }

    public function testServerErrorResponseThrows(): void
    {
        $log = new Logger('test');
        $log->pushHandler($this->handlerForStatus(500));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/HTTP 500/');

        $log->info($this->faker()->sentence());
    }

    public function testHandler(): void
    {
        $host = $_ENV['O2_HOST'];
        $organizationId = $_ENV['O2_ORGANIZATION_ID'];
        $streamName = $_ENV['O2_STREAM_NAME'];
        $username = $_ENV['O2_USERNAME'];
        $password = $_ENV['O2_PASSWORD'];
        if (empty($host) || empty($organizationId) || empty($streamName) || empty($username) || empty($password)) {
            $this->markTestSkipped('OpenObserve environment variables are not set.');
        }

        $handler = new OpenObserveHandler($host, $organizationId, $streamName, $username, $password);

        $message = $this->faker()->sentence();

        $log = new Logger('test');
        $log->pushHandler($handler);
        $log->info($message, ['foo' => 'bar']);

        // Since the handler does not bubble, we cannot check the records directly.
        // Instead, we can only ensure that no exceptions were thrown during logging.
        $this->assertTrue(true);
    }

    public function testIgnoreFailure(): void
    {
        $host = $_ENV['O2_HOST'];
        $organizationId = $_ENV['O2_ORGANIZATION_ID'];
        $streamName = $_ENV['O2_STREAM_NAME'];
        $username = $_ENV['O2_USERNAME'];
        $password = $_ENV['O2_PASSWORD'];
        if (empty($host) || empty($organizationId) || empty($streamName) || empty($username) || empty($password)) {
            $this->markTestSkipped('OpenObserve environment variables are not set.');
        }
        
        $log = new Logger('test');
        $message = $this->faker()->sentence();

        $handler = new OpenObserveHandler('http://invalid-host', 'invalid-org', 'invalid-stream', 'user', 'pass', true);
        
        $log->pushHandler($handler);
        $log->info($message, ['foo' => 'bar']);

        $log->popHandler();

        $handler = new OpenObserveHandler('http://invalid-host', 'invalid-org', 'invalid-stream', 'user', 'pass', false);
        $log->pushHandler($handler);

        $this->expectException(\Exception::class);
        $log->info($message, ['foo' => 'bar']);
    }
}