<?php

namespace App\Logging;

use App\Support\Observability\SensitiveDataRedactor;

/**
 * Applies the same redaction policy to Monolog context and extra fields.
 */
final class RedactSensitiveContextProcessor
{
    /**
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    public function __invoke(array $record): array
    {
        foreach (['context', 'extra'] as $section) {
            if (isset($record[$section]) && is_array($record[$section])) {
                $record[$section] = SensitiveDataRedactor::redactArray($record[$section]);
            }
        }

        if (isset($record['message']) && is_string($record['message'])) {
            $record['message'] = SensitiveDataRedactor::redactMessage($record['message']);
        }

        return $record;
    }
}
