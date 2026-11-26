<?php

namespace App\Logging;

use App\Support\Observability\SensitiveDataRedactor;
use Monolog\LogRecord;

/**
 * Applies the same redaction policy to Monolog 3 context and extra fields.
 */
final class RedactSensitiveContextProcessor implements \Monolog\Processor\ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        // Monolog 3 records are immutable; return a transformed copy.
        return $record->with(
            message: SensitiveDataRedactor::redactMessage($record->message),
            context: SensitiveDataRedactor::redactArray($record->context),
            extra: SensitiveDataRedactor::redactArray($record->extra),
        );
    }
}
