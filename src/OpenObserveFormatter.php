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

        // Filtering a batch can leave gaps in its keys; JSON must remain a list.
        return $this->toJson(array_values($formatted), true);
    }

    /**
     * @inheritDoc
     */
    protected function normalizeRecord(LogRecord $record): array
    {
        $normalized = parent::normalizeRecord($record);

        // OpenObserve reads a numeric _timestamp as microseconds since the epoch,
        // so seconds would land every log in 1970. 'Uu' gives seconds plus the
        // six-digit microsecond part, which is exactly that value.
        $normalized['_timestamp'] = (int) $record->datetime->format('Uu');

        return $normalized;
    }
}
