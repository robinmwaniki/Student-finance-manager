<?php

namespace App\\Services\\LegacyImport;

use DateTimeImmutable;
use InvalidArgumentException;

final class LegacyValueMapper
{
    public function amount(mixed $value, bool $allowNegative = false): string
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            throw new InvalidArgumentException('Amount must be a numeric scalar.');
        }

        $amount = trim((string) $value);

        if (! preg_match('/^-?\\d{1,12}(?:\\.\\d{1,2})?$/', $amount)) {
            throw new InvalidArgumentException('Amount must have at most 12 whole digits and 2 decimal places.');
        }

        if (! $allowNegative && str_starts_with($amount, '-')) {
            throw new InvalidArgumentException('Negative amounts are not supported for this record.');
        }

        return number_format((float) $amount, 2, '.', '');
    }

    public function date(mixed $value, bool $nullable = false): ?string
    {
        if ($value === null || trim((string) $value) === '' || $value === '0000-00-00') {
            if ($nullable) {
                return null;
            }

            throw new InvalidArgumentException('A valid date is required.');
        }

        $value = (string) $value;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if (! $date || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('Date must use a valid YYYY-MM-DD value.');
        }

        return $value;
    }

    public function month(mixed $value, ?string $fallbackDate = null): string
    {
        if (is_string($value) && preg_match('/^\\d{4}-(0[1-9]|1[0-2])$/', $value)) {
            return $value;
        }

        if ($fallbackDate !== null) {
            return substr($this->date($fallbackDate), 0, 7);
        }

        throw new InvalidArgumentException('Month must use YYYY-MM format.');
    }

    public function boolean(mixed $value): bool
    {
        return match (strtolower(trim((string) $value))) {
            '1', '1.0', 'true', 'yes', 'on' => true,
            '0', '0.0', 'false', 'no', 'off' => false,
            default => throw new InvalidArgumentException('Boolean value is not recognized.'),
        };
    }

    public function bcryptHash(mixed $value): string
    {
        if (! is_string($value) || ! preg_match('/^\\$2[aby]\\$\\d{2}\\$[.\\/A-Za-z0-9]{53}$/', $value)) {
            throw new InvalidArgumentException('Password hash is not a supported bcrypt hash.');
        }

        return $value;
    }

    public function debtType(mixed $value): string
    {
        return match ($value) {
            'borrowed' => 'borrowed',
            'owed' => 'lent',
            default => throw new InvalidArgumentException('Debt type is not supported.'),
        };
    }

    public function recurringStatus(mixed $value): array
    {
        return match ($value) {
            'active' => ['status' => 'active', 'active' => true],
            'inactive' => ['status' => 'paused', 'active' => false],
            default => throw new InvalidArgumentException('Recurring status is not supported.'),
        };
    }

    public function recurringPeriodKey(string $month): string
    {
        return 'M:'.$this->month($month);
    }
}
