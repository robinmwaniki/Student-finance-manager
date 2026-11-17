<?php

namespace App\Support\Observability;

/**
 * Redacts financial and authentication values before they enter observability systems.
 */
final class SensitiveDataRedactor
{
    private const SENSITIVE_KEY_PATTERN = '/password|passwd|secret|token|authorization|cookie|session|account|amount|balance|transaction|description|email|phone|ssn|national.?id|cvv|card.?number/i';

    /**
     * Replace values attached to sensitive keys, including nested request/log contexts.
     *
     */
    public static function redactArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && preg_match(self::SENSITIVE_KEY_PATTERN, $key) === 1) {
                $data[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                $data[$key] = self::redactArray($value);
            }
        }

        return $data;
    }

    /**
     * Redact common key/value disclosures in free-form log and exception messages.
     */
    public static function redactMessage(string $message): string
    {
        return (string) preg_replace(
            '/\b(password|passwd|secret|token|authorization|account(?:_number)?|amount|balance|transaction(?:_description)?|email|phone)\b\s*[:=]\s*("[^"]*"|\'[^\']*\'|[^\s,;]+)/i',
            '$1=[REDACTED]',
            $message
        );
    }
}
