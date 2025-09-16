<?php

namespace Minhyung\Monolog;

use Monolog\Formatter\NormalizerFormatter;
use Monolog\LogRecord;

class OpenObserveFormatter extends NormalizerFormatter
{
    /**
     * @inheritDoc
     */
    public function format(LogRecord $record): string
    {
        $normalized = $this->normalizeRecord($record);

        return $this->toJson($normalized, true);
    }

    /**
     * @inheritDoc
     */
    public function formatBatch(array $records): string
    {
        $formatted = array_map(fn (LogRecord $record) => $this->normalizeRecord($record), $records);

        return $this->toJson($formatted, true);
    }

    /**
     * @inheritDoc
     */
    protected function normalizeRecord(LogRecord $record): array
    {
        $normalized = parent::normalizeRecord($record);

        $normalized['_timestamp'] = $record->datetime->getTimestamp();

        return $normalized;
    }
}
