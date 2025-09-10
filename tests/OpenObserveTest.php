<?php

namespace Minhyung\Monolog\Tests;

use Minhyung\Monolog\OpenObserveFormatter;
use Minhyung\Monolog\OpenObserveHandler;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

class OpenObserveTest extends TestCase
{
    protected $faker;

    protected function faker()
    {
        return $this->faker ??= \Faker\Factory::create();
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
}