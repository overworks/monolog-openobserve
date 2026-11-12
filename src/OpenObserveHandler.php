<?php

namespace Minhyung\Monolog;

use Monolog\Formatter\FormatterInterface;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Handler\Curl\Util as CurlUtil;
use Monolog\Handler\MissingExtensionException;
use Monolog\Level;
use Monolog\LogRecord;

class OpenObserveHandler extends AbstractProcessingHandler
{
    /**
     * Constructor.
     * 
     * @param string $host The OpenObserve host URL.
     * @param string $organizationId The organization ID.
     * @param string $streamName The stream name.
     * @param string $username The username for authentication.
     * @param string $password The password for authentication.
     * @param bool|\Closure(\Throwable, list<LogRecord>): void $ignoreFailure What to do when sending fails.
     *        false rethrows the exception and true swallows it. A Closure is called instead of throwing,
     *        with the exception and the records that could not be sent, so they can be reported or passed
     *        on to another handler. It must not log back to this handler, as that would recurse. Anything
     *        it throws propagates to the caller.
     * @param int|string|Level $level The minimum logging level at which this handler will be triggered.
     * @param bool $bubble Whether the messages that are handled can bubble up the stack or not.
     * @param float $connectTimeout Seconds to wait for the connection to OpenObserve before giving up.
     * @param float $timeout Seconds to allow for the whole request before giving up. Keeps a stalled
     *                       OpenObserve from blocking the application indefinitely. 0 disables the limit.
     */
    public function __construct(
        protected string $host,
        protected string $organizationId,
        protected string $streamName,
        protected string $username,
        protected string $password,
        protected bool|\Closure $ignoreFailure = false,
        int|string|Level $level = Level::Debug,
        bool $bubble = true,
        protected float $connectTimeout = 2.0,
        protected float $timeout = 5.0
    ) {
        if (!\extension_loaded('curl')) {
            throw new MissingExtensionException('The curl extension is needed to use the OpenObserveHandler');
        }

        parent::__construct($level, $bubble);
    }

    public static function builder(string $host, string $organizationId, string $streamName): OpenObserveHandlerBuilder
    {
        return new OpenObserveHandlerBuilder($host, $organizationId, $streamName);
    }

    /**
     * @inheritDoc
     */
    public function handleBatch(array $records): void
    {
        $records = array_filter($records, [$this, 'isHandling']);

        if (\count($records) === 0) {
            return;
        }

        if (\count($this->processors) > 0) {
            foreach ($records as $index => $record) {
                $records[$index] = $this->processRecord($record);
            }
        }

        $formattedRecords = $this->getFormatter()->formatBatch($records);

        $this->send($formattedRecords, array_values($records));
    }

    /**
     * @inheritDoc
     */
    protected function write(LogRecord $record): void
    {
        $this->send($record->formatted, [$record]);
    }

    /**
     * Gets the default formatter.
     *
     * Overwrite this if the LineFormatter is not a good default for your handler.
     */
    protected function getDefaultFormatter(): FormatterInterface
    {
        return new OpenObserveFormatter();
    }

    /**
     * Sends the payload to OpenObserve.
     * 
     * @param string $payload The JSON payload to send.
     * @param list<LogRecord> $records The records the payload was built from, handed to the
     *                                 ignoreFailure closure if sending fails.
     * @return void
     * @throws \RuntimeException If the request fails or is rejected and ignoreFailure is false.
     */
    protected function send(string $payload, array $records): void
    {
        try {
            $url = rtrim($this->host, '/') . "/api/{$this->organizationId}/{$this->streamName}/_json";

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Basic ' . base64_encode("{$this->username}:{$this->password}"),
            ]);
            // The whole-second CURLOPT_TIMEOUT / CURLOPT_CONNECTTIMEOUT would floor
            // a fractional value, so use the millisecond variants to honour it.
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, (int) round($this->connectTimeout * 1000));
            curl_setopt($ch, CURLOPT_TIMEOUT_MS, (int) round($this->timeout * 1000));

            // A single attempt keeps the timeout bound to this log call. Retrying
            // a timed-out POST can also duplicate records already ingested.
            $result = CurlUtil::execute($ch, 1);
            if ($result === false) {
                throw new \RuntimeException("Failed to send log to OpenObserve at {$url}");
            }

            // curl only reports transport-level failures, so a 401/403/404 arrives
            // here as a perfectly successful request. Check the status explicitly.
            $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            if ($status < 200 || $status >= 300) {
                throw new \RuntimeException("OpenObserve returned HTTP {$status} for {$url}: {$result}");
            }

            // A successful HTTP response can still report rejected records.
            $response = json_decode($result, true);
            foreach ($response['status'] ?? [] as $streamStatus) {
                if (($streamStatus['failed'] ?? 0) > 0) {
                    throw new \RuntimeException("OpenObserve failed to ingest records at {$url}: {$result}");
                }
            }
        } catch (\Exception $e) {
            if ($this->ignoreFailure === false) {
                throw $e;
            }

            // The failure is being swallowed on purpose, but a caller that wants
            // to know about it can supply a closure to receive it.
            if ($this->ignoreFailure instanceof \Closure) {
                ($this->ignoreFailure)($e, $records);
            }
        }
    }
}
