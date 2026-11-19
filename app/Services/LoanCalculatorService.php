<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

class LoanCalculatorService
{
    /**
     * Calculate a rounded amortisation schedule using integer cents for balances and payments.
     *
     * @return array{periods: int, periodic_payment: float, total_paid: float, total_interest: float, schedule: array<int, array<string, int|float|string>>}
     */
    public function calculate(float $principal, float $annualRatePercent, int $termMonths, string $frequency): array
    {
        $periodsPerYear = match ($frequency) {
            'monthly' => 12,
            'weekly' => 52,
            'biweekly' => 26,
            default => throw new InvalidArgumentException('Unsupported repayment frequency.'),
        };

        if ($principal <= 0 || $annualRatePercent < 0 || $annualRatePercent > 100 || $termMonths < 1 || $termMonths > 360) {
            throw new InvalidArgumentException('Enter a positive principal, a rate from 0 to 100%, and a term from 1 to 360 months.');
        }

        $periods = (int) ceil($termMonths * $periodsPerYear / 12);
        $balance = (int) round($principal * 100);
        $periodicRate = ($annualRatePercent / 100) / $periodsPerYear;
        $payment = $periodicRate === 0.0
            ? (int) round($balance / $periods)
            : (int) round($balance * ($periodicRate / (1 - (1 + $periodicRate) ** (-$periods))));
        $start = CarbonImmutable::now(config('app.timezone'));
        $schedule = [];
        $totalPaid = 0;
        $totalInterest = 0;

        for ($period = 1; $period <= $periods && $balance > 0; $period++) {
            $interest = (int) round($balance * $periodicRate);
            $due = match ($frequency) {
                'monthly' => $start->addMonthsNoOverflow($period),
                'weekly' => $start->addWeeks($period),
                'biweekly' => $start->addWeeks($period * 2),
            };
            $actualPayment = min($payment, $balance + $interest);
            if ($period === $periods) {
                $actualPayment = $balance + $interest;
            }
            $principalPaid = min($balance, max(0, $actualPayment - $interest));
            $balance -= $principalPaid;
            $totalPaid += $actualPayment;
            $totalInterest += $interest;

            $schedule[] = [
                'period' => $period,
                'due_date' => $due->toDateString(),
                'payment' => $actualPayment / 100,
                'principal' => $principalPaid / 100,
                'interest' => $interest / 100,
                'balance' => $balance / 100,
            ];
        }

        return [
            'periods' => count($schedule),
            'periodic_payment' => ($schedule[0]['payment'] ?? 0),
            'total_paid' => $totalPaid / 100,
            'total_interest' => $totalInterest / 100,
            'schedule' => $schedule,
        ];
    }
}
