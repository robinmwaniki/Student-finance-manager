<?php

namespace App\Services;

use InvalidArgumentException;

class BillSplitService
{
    /**
     * Distribute a bill in integer cents. Remainder cents go to the first participants.
     *
     * @return array{subtotal: float, tip: float, total: float, participants: int, shares: array<int, float>}
     */
    public function split(float $subtotal, int $participants, float $tipPercent = 0): array
    {
        if ($subtotal <= 0 || $participants < 1 || $participants > 100 || $tipPercent < 0 || $tipPercent > 100) {
            throw new InvalidArgumentException('Enter a positive bill, 1–100 participants and a tip from 0 to 100%.');
        }

        $subtotalCents = (int) round($subtotal * 100);
        $tipCents = (int) round($subtotalCents * ($tipPercent / 100));
        $totalCents = $subtotalCents + $tipCents;
        $baseShare = intdiv($totalCents, $participants);
        $remainder = $totalCents % $participants;
        $shares = [];

        for ($person = 1; $person <= $participants; $person++) {
            $shares[] = ($baseShare + ($person <= $remainder ? 1 : 0)) / 100;
        }

        return [
            'subtotal' => $subtotalCents / 100,
            'tip' => $tipCents / 100,
            'total' => $totalCents / 100,
            'participants' => $participants,
            'shares' => $shares,
        ];
    }
}
