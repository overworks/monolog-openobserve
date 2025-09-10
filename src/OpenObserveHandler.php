<?php

namespace Minhyung\Monolog;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Monolog\Formatter\FormatterInterface;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

class OpenObserveHandler extends AbstractProcessingHandler
{
    /**
     * Constructor.
     * 
     * @param string $host The OpenObserve host URL.
     * @param string $organizationId The organization ID.
     * @param string $stream The stream name.
     * @param string $username The username for authentication.
     * @param string $password The password for authentication.
     * @param bool $ignoreFailure Whether to ignore failures when sending logs.
     * @param int|string|Level $level The minimum logging level at which this handler will be triggered.
     * @param bool $bubble Whether the messages that are handled can bubble up the stack or not.
     */
    public function __construct(
        protected string $host,
        protected string $organizationId,
        protected string $stream,
        protected string $username,
        protected string $password,
        protected bool $ignoreFailure = false,
        int|string|Level $level = Level::Debug,
        bool $bubble = true
    ) {
        parent::__construct($level, $bubble);
    }

    /**
     * @inheritDoc
     */
    public function handle(LogRecord $record): bool
    {
        if (!$this->isHandling($record)) {
            return false;
        }

        $record = $this->processRecord($record);

        $record->formatted = $this->getFormatter()->formatBatch([$record]);

        $this->write($record);

        return false === $this->bubble;
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

        $this->send($formattedRecords);
    }

    /**
     * @inheritDoc
     */
    protected function write(LogRecord $record): void
    {
        $this->send($record->formatted);
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
     * @return void
     * @throws \Throwable If the request fails and ignoreFailure is false.
     */
    protected function send(string $payload): void
    {
        try {
            $client = new Client(['base_uri' => rtrim($this->host, '/')]);
            $client->post("/api/{$this->organizationId}/{$this->stream}/_json", [
                RequestOptions::AUTH => [$this->username, $this->password],
                RequestOptions::HEADERS => [
                    'Content-Type' => 'application/json',
                ],
                RequestOptions::BODY => $payload,
            ]);
        } catch (\Throwable $e) {
            if (! $this->ignoreFailure) {
                throw $e;
            }
        }
    }
}
