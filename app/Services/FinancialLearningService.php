<?php

namespace App\Services;

use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Models\UserFinancialStreak;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class FinancialLearningService
{
    public function __construct(private readonly AchievementService $achievements)
    {
    }

    public function questionForDate(CarbonImmutable $date): QuizQuestion
    {
        $questionCount = QuizQuestion::query()->where('is_active', true)->count();

        abort_if($questionCount === 0, 503, 'The quiz question bank is not available.');

        $index = ((int) $date->format('z')) % $questionCount;

        return QuizQuestion::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->skip($index)
            ->firstOrFail();
    }

    /**
     * Record one server-scored attempt per user-local calendar day.
     *
     * @return array{attempt: QuizAttempt, streak: UserFinancialStreak, already_attempted: bool}
     */
    public function submitAnswer(User $user, string $selectedOption): array
    {
        return DB::transaction(function () use ($user, $selectedOption): array {
            $lockedUser = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $streak = UserFinancialStreak::query()->where('user_id', $lockedUser->getKey())->lockForUpdate()->first();
            $timezone = $streak?->timezone ?: config('app.timezone');
            $today = CarbonImmutable::now($timezone)->startOfDay();
            $quizDate = $today->toDateString();

            $existing = $lockedUser->quizAttempts()->whereDate('quiz_date', $quizDate)->with('question')->first();

            if ($existing !== null) {
                return [
                    'attempt' => $existing,
                    'streak' => $streak ?? UserFinancialStreak::query()->where('user_id', $lockedUser->getKey())->firstOrFail(),
                    'already_attempted' => true,
                ];
            }

            $question = $this->questionForDate($today);
            $isCorrect = $selectedOption === $question->correct_option;
            $points = $isCorrect ? 10 : 0;

            $attempt = $lockedUser->quizAttempts()->create([
                'question_id' => $question->getKey(),
                'quiz_date' => $quizDate,
                'selected_option' => $selectedOption,
                'is_correct' => $isCorrect,
                'points_awarded' => $points,
                'answered_at' => now($timezone),
            ]);

            if ($points > 0) {
                $lockedUser->increment('points', $points);
            }

            if ($streak === null) {
                $streak = UserFinancialStreak::query()->create([
                    'user_id' => $lockedUser->getKey(),
                    'timezone' => $timezone,
                ]);
            }

            $lastDate = $streak->last_activity_date?->toDateString();

            if ($lastDate === null) {
                $streak->current_streak = 1;
            } elseif ($lastDate === $quizDate) {
                // A same-day replay is not a new streak day.
                $streak->current_streak = max(1, $streak->current_streak);
            } elseif ($lastDate === $today->subDay()->toDateString()) {
                $streak->current_streak++;
            } else {
                $streak->current_streak = 1;
            }

            $streak->longest_streak = max($streak->longest_streak, $streak->current_streak);
            $streak->last_activity_date = $quizDate;
            $streak->lock_version++;
            $streak->save();

            $this->achievements->evaluate($lockedUser);

            return [
                'attempt' => $attempt->load('question'),
                'streak' => $streak,
                'already_attempted' => false,
            ];
        });
    }
}
