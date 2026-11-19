<?php

namespace App\Services;

use App\Models\Achievement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AchievementService
{
    /**
     * Evaluate eligibility from persisted server-side records only.
     */

    /**
     * Return server-calculated progress for the full achievement catalogue.
     *
     * @return array<int, array<string, mixed>>
     */
    public function progress(User $user): array
    {
        $correctAnswers = $user->quizAttempts()->where('is_correct', true)->count();
        $attemptCount = $user->quizAttempts()->count();
        $longestStreak = $user->financialStreak?->longest_streak ?? 0;
        $unlockedCodes = $user->achievements()->whereNotNull('unlocked_at')->pluck('code')->all();

        $milestones = [
            ['code' => 'first_income', 'title' => 'First income recorded', 'description' => 'Record your first income transaction.', 'points' => 15, 'current' => (int) $user->income()->exists(), 'target' => 1],
            ['code' => 'first_expense', 'title' => 'First expense tracked', 'description' => 'Record your first expense transaction.', 'points' => 15, 'current' => (int) $user->expenses()->exists(), 'target' => 1],
            ['code' => 'first_saving', 'title' => 'First savings contribution', 'description' => 'Record your first savings contribution.', 'points' => 20, 'current' => (int) $user->savings()->exists(), 'target' => 1],
            ['code' => 'first_budget', 'title' => 'Budget builder', 'description' => 'Create your first monthly budget.', 'points' => 20, 'current' => (int) $user->budgets()->exists(), 'target' => 1],
            ['code' => 'first_quiz', 'title' => 'Learning in motion', 'description' => 'Complete your first daily quiz.', 'points' => 10, 'current' => min(1, $attemptCount), 'target' => 1],
            ['code' => 'quiz_correct_10', 'title' => 'Financial learner', 'description' => 'Answer ten quiz questions correctly.', 'points' => 30, 'current' => min(10, $correctAnswers), 'target' => 10],
            ['code' => 'quiz_correct_25', 'title' => 'Money knowledge builder', 'description' => 'Answer twenty-five quiz questions correctly.', 'points' => 60, 'current' => min(25, $correctAnswers), 'target' => 25],
            ['code' => 'streak_3', 'title' => 'Three-day learning streak', 'description' => 'Complete the daily quiz on three consecutive local days.', 'points' => 20, 'current' => min(3, $longestStreak), 'target' => 3],
            ['code' => 'streak_7', 'title' => 'Week of consistency', 'description' => 'Complete the daily quiz on seven consecutive local days.', 'points' => 50, 'current' => min(7, $longestStreak), 'target' => 7],
            ['code' => 'streak_30', 'title' => 'Thirty-day commitment', 'description' => 'Complete the daily quiz on thirty consecutive local days.', 'points' => 150, 'current' => min(30, $longestStreak), 'target' => 30],
        ];

        return array_map(
            fn (array $milestone): array => [
                ...$milestone,
                'unlocked' => in_array($milestone['code'], $unlockedCodes, true),
            ],
            $milestones,
        );
    }

    public function evaluate(User $user): int
    {
        return DB::transaction(function () use ($user): int {
            $lockedUser = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $correctAnswers = $lockedUser->quizAttempts()->where('is_correct', true)->count();
            $streak = $lockedUser->financialStreak;
            $rules = [
                'first_income' => [
                    'title' => 'First income recorded',
                    'description' => 'You recorded your first income transaction.',
                    'points' => 15,
                    'eligible' => $lockedUser->income()->exists(),
                ],
                'first_expense' => [
                    'title' => 'First expense tracked',
                    'description' => 'You recorded your first expense transaction.',
                    'points' => 15,
                    'eligible' => $lockedUser->expenses()->exists(),
                ],
                'first_saving' => [
                    'title' => 'First savings contribution',
                    'description' => 'You recorded your first savings contribution.',
                    'points' => 20,
                    'eligible' => $lockedUser->savings()->exists(),
                ],
                'first_budget' => [
                    'title' => 'Budget builder',
                    'description' => 'You created your first monthly budget.',
                    'points' => 20,
                    'eligible' => $lockedUser->budgets()->exists(),
                ],
                'first_quiz' => [
                    'title' => 'Learning in motion',
                    'description' => 'You completed your first financial-literacy quiz.',
                    'points' => 10,
                    'eligible' => $lockedUser->quizAttempts()->exists(),
                ],
                'quiz_correct_10' => [
                    'title' => 'Financial learner',
                    'description' => 'You answered ten quiz questions correctly.',
                    'points' => 30,
                    'eligible' => $correctAnswers >= 10,
                ],
                'quiz_correct_25' => [
                    'title' => 'Money knowledge builder',
                    'description' => 'You answered twenty-five quiz questions correctly.',
                    'points' => 60,
                    'eligible' => $correctAnswers >= 25,
                ],
                'streak_3' => [
                    'title' => 'Three-day learning streak',
                    'description' => 'You completed the daily quiz on three consecutive local days.',
                    'points' => 20,
                    'eligible' => ($streak?->longest_streak ?? 0) >= 3,
                ],
                'streak_7' => [
                    'title' => 'Week of consistency',
                    'description' => 'You completed the daily quiz on seven consecutive local days.',
                    'points' => 50,
                    'eligible' => ($streak?->longest_streak ?? 0) >= 7,
                ],
                'streak_30' => [
                    'title' => 'Thirty-day commitment',
                    'description' => 'You completed the daily quiz on thirty consecutive local days.',
                    'points' => 150,
                    'eligible' => ($streak?->longest_streak ?? 0) >= 30,
                ],
            ];

            $newAwards = 0;

            foreach ($rules as $code => $rule) {
                if (! $rule['eligible']) {
                    continue;
                }

                $inserted = Achievement::query()->insertOrIgnore([
                    'user_id' => $lockedUser->getKey(),
                    'code' => $code,
                    'title' => $rule['title'],
                    'description' => $rule['description'],
                    'points' => $rule['points'],
                    'unlocked_at' => now(config('app.timezone')),
                    'created_at' => now(config('app.timezone')),
                    'updated_at' => now(config('app.timezone')),
                ]);

                if ($inserted === 1) {
                    $lockedUser->increment('points', $rule['points']);
                    $newAwards++;
                }
            }

            return $newAwards;
        });
    }
}
