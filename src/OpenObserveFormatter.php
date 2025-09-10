<?php

namespace Minhyung\Monolog;

use Monolog\Formatter\JsonFormatter;
use Monolog\LogRecord;

class OpenObserveFormatter extends JsonFormatter
{
    protected function normalizeRecord(LogRecord $record): array
    {
        $normalized = parent::normalizeRecord($record);

        $normalized['_timestamp'] = $record->datetime->getTimestamp();

        return $normalized;
    }
}
