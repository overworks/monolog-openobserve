<?php

namespace Minhyung\Monolog;

use Monolog\Formatter\JsonFormatter;
use Monolog\LogRecord;

class OpenObserveFormatter extends JsonFormatter
{
    public function __construct(int $batchMode = self::BATCH_MODE_JSON, bool $appendNewline = true, bool $ignoreEmptyContextAndExtra = true, bool $includeStacktraces = true)
    {
        parent::__construct($batchMode, $appendNewline, $ignoreEmptyContextAndExtra, $includeStacktraces);
    }

    protected function normalizeRecord(LogRecord $record): array
    {
        $normalized = parent::normalizeRecord($record);

        $normalized['_timestamp'] = $record->datetime->getTimestamp();

        return $normalized;
    }
}
