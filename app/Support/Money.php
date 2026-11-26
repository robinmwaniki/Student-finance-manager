<?php

namespace App\Support;

use InvalidArgumentException;

final class Money
{
    public static function toMinorUnits(string|int $amount): int
    {
        $value = trim((string) $amount);

        if (preg_match('/^([+-]?)(\d+)(?:\.(\d{1,2}))?$/D', $value, $matches) !== 1) {
            throw new InvalidArgumentException('Money amounts must be plain decimal values with at most two decimal places.');
        }

        $whole = (int) $matches[2];
        $fraction = (int) str_pad($matches[3] ?? '', 2, '0');

        $minorUnits = ($whole * 100) + $fraction;

        return ($matches[1] ?? '') === '-' ? -$minorUnits : $minorUnits;
    }

    public static function fromMinorUnits(int $amount): string
    {
        $sign = $amount < 0 ? '-' : '';
        $absolute = abs($amount);

        return $sign.intdiv($absolute, 100).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<int, string|int>  $amounts
     */
    public static function average(array $amounts): string
    {
        if ($amounts === []) {
            return '0.00';
        }

        $total = array_sum(array_map(self::toMinorUnits(...), $amounts));
        $count = count($amounts);
        $average = intdiv($total, $count);
        $remainder = $total % $count;

        if (abs($remainder) * 2 >= $count) {
            $average += $total < 0 ? -1 : 1;
        }

        return self::fromMinorUnits($average);
    }

    public static function subtract(string|int ...$amounts): string
    {
        if ($amounts === []) {
            return '0.00';
        }

        $result = self::toMinorUnits(array_shift($amounts));

        foreach ($amounts as $amount) {
            $result -= self::toMinorUnits($amount);
        }

        return self::fromMinorUnits($result);
    }
}
