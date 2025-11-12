<?php

namespace Minhyung\Monolog;

use Monolog\Formatter\FormatterInterface;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Handler\Curl\Util as CurlUtil;
use Monolog\Handler\MissingExtensionException;
use Monolog\Level;
use Monolog\LogRecord;
use RuntimeException;

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
     * @param bool $ignoreFailure Whether to ignore failures when sending logs.
     * @param int|string|Level $level The minimum logging level at which this handler will be triggered.
     * @param bool $bubble Whether the messages that are handled can bubble up the stack or not.
     */
    public function __construct(
        protected string $host,
        protected string $organizationId,
        protected string $streamName,
        protected string $username,
        protected string $password,
        protected bool $ignoreFailure = false,
        int|string|Level $level = Level::Debug,
        bool $bubble = true
    ) {
        if (!\extension_loaded('curl')) {
            throw new MissingExtensionException('The curl extension is needed to use the OpenObserveHandler');
        }

        parent::__construct($level, $bubble);
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

        $result = CurlUtil::execute($ch);
        if ($result === false && ! $this->ignoreFailure) {
            throw new RuntimeException("Failed to send log to OpenObserve at {$url}");
        }
    }
}
