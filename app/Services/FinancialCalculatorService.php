<?php

namespace App\Services;

class FinancialCalculatorService
{
    private const PERIODS_PER_YEAR = [
        'weekly' => 52,
        'biweekly' => 26,
        'monthly' => 12,
        'quarterly' => 4,
    ];

    /**
     * Build a reducing-balance amortisation schedule using integer minor units.
     *
     * @return array{frequency: string, periods: int, periodic_payment: string, total_interest: string, total_repayment: string, schedule: array<int, array{period: int, payment: string, principal: string, interest: string, balance: string}>}
     */
    public function loanSchedule(float $principal, float $annualRate, int $termMonths, string $frequency): array
    {
        $periodsPerYear = self::PERIODS_PER_YEAR[$frequency];
        $periods = max(1, (int) round($termMonths * $periodsPerYear / 12));
        $principalCents = (int) round($principal * 100);
        $periodicRate = ($annualRate / 100) / $periodsPerYear;
        $paymentCents = $periodicRate === 0.0
            ? (int) ceil($principalCents / $periods)
            : (int) round($principalCents * $periodicRate / (1 - (1 + $periodicRate) ** (-$periods)));

        $balanceCents = $principalCents;
        $totalInterestCents = 0;
        $totalPaymentCents = 0;
        $schedule = [];

        for ($period = 1; $period <= $periods; $period++) {
            $interestCents = (int) round($balanceCents * $periodicRate);
            $principalPartCents = min($balanceCents, max(0, $paymentCents - $interestCents));
            $actualPaymentCents = $principalPartCents + $interestCents;

            if ($period === $periods && $balanceCents > $principalPartCents) {
                $principalPartCents = $balanceCents;
                $actualPaymentCents = $principalPartCents + $interestCents;
            }

            $balanceCents -= $principalPartCents;
            $totalInterestCents += $interestCents;
            $totalPaymentCents += $actualPaymentCents;
            $schedule[] = [
                'period' => $period,
                'payment' => $this->money($actualPaymentCents),
                'principal' => $this->money($principalPartCents),
                'interest' => $this->money($interestCents),
                'balance' => $this->money($balanceCents),
            ];
        }

        return [
            'frequency' => $frequency,
            'periods' => $periods,
            'periodic_payment' => $this->money($paymentCents),
            'total_interest' => $this->money($totalInterestCents),
            'total_repayment' => $this->money($totalPaymentCents),
            'schedule' => $schedule,
        ];
    }

    /**
     * Split total and tip in cents; remainder cents go to the first participants.
     *
     * @return array{subtotal: string, tip: string, total: string, people: int, equal_share: string, shares: array<int, string>, remainder_cents: int}
     */
    public function splitBill(float $subtotal, int $people, float $tipPercent): array
    {
        $subtotalCents = (int) round($subtotal * 100);
        $tipCents = (int) round($subtotalCents * ($tipPercent / 100));
        $totalCents = $subtotalCents + $tipCents;
        $equalCents = intdiv($totalCents, $people);
        $remainder = $totalCents % $people;
        $shares = [];

        for ($person = 0; $person < $people; $person++) {
            $shares[] = $this->money($equalCents + ($person < $remainder ? 1 : 0));
        }

        return [
            'subtotal' => $this->money($subtotalCents),
            'tip' => $this->money($tipCents),
            'total' => $this->money($totalCents),
            'people' => $people,
            'equal_share' => $this->money($equalCents),
            'shares' => $shares,
            'remainder_cents' => $remainder,
        ];
    }

    private function money(int $minorUnits): string
    {
        return number_format($minorUnits / 100, 2, '.', '');
    }
}
