<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseCaptureDraft;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExpenseCaptureService
{
    public function createDraft(User $user, string $source, array $candidate, string $fingerprint): ExpenseCaptureDraft
    {
        return $user->expenseCaptureDrafts()->firstOrCreate(
            ['fingerprint' => $fingerprint],
            [
                'source' => $source,
                'merchant' => $candidate['merchant'] ?? null,
                'category' => $candidate['category'] ?? null,
                'amount' => $candidate['amount'] ?? null,
                'transaction_date' => $candidate['transaction_date'] ?? null,
                'tag' => $candidate['tag'] ?? null,
                'source_reference' => $candidate['reference'] ?? null,
                'status' => 'pending',
                'submission_token' => (string) Str::uuid(),
                'expires_at' => now(config('app.timezone'))->addDay(),
            ],
        );
    }

    public function confirm(User $user, int $draftId, int $expectedVersion, array $data): Expense
    {
        return DB::transaction(function () use ($user, $draftId, $expectedVersion, $data): Expense {
            $draft = $user->expenseCaptureDrafts()->whereKey($draftId)->lockForUpdate()->firstOrFail();

            abort_unless($draft->status === 'pending', 409, 'This capture draft is no longer awaiting confirmation.');
            abort_unless($draft->lock_version === $expectedVersion, 409, 'This draft changed. Refresh it before confirming.');

            $expense = $user->expenses()->firstOrCreate(
                ['submission_token' => $draft->submission_token],
                [
                    'category' => $data['category'],
                    'amount' => $data['amount'],
                    'transaction_date' => $data['transaction_date'],
                    'tag' => $data['tag'] ?? null,
                ],
            );

            $draft->forceFill([
                'status' => 'confirmed',
                'expense_id' => $expense->getKey(),
                'lock_version' => $expectedVersion + 1,
            ])->save();

            return $expense;
        });
    }

    public function discard(User $user, int $draftId, int $expectedVersion): void
    {
        DB::transaction(function () use ($user, $draftId, $expectedVersion): void {
            $draft = $user->expenseCaptureDrafts()->whereKey($draftId)->lockForUpdate()->firstOrFail();

            abort_unless($draft->status === 'pending', 409, 'This capture draft is no longer pending.');
            abort_unless($draft->lock_version === $expectedVersion, 409, 'This draft changed. Refresh it before discarding.');

            $draft->forceFill([
                'status' => 'discarded',
                'lock_version' => $expectedVersion + 1,
            ])->save();
        });
    }
}
