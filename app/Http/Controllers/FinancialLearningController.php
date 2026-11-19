<?php

namespace App\Http\Controllers;

use App\Services\AchievementService;
use App\Services\FinancialLearningService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FinancialLearningController extends Controller
{
    public function index(
        Request $request,
        FinancialLearningService $learning,
        AchievementService $achievements,
    ): View {
        $user = $request->user();
        $achievements->evaluate($user);
        $streak = $user->financialStreak;
        $timezone = $streak?->timezone ?: config('app.timezone');
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $question = $learning->questionForDate($today);
        $attempt = $user->quizAttempts()
            ->whereDate('quiz_date', $today->toDateString())
            ->with('question')
            ->first();

        return view('finance.learning.index', [
            'points' => $user->fresh()->points,
            'streak' => $streak,
            'question' => $question,
            'attempt' => $attempt,
            'achievements' => $user->achievements()->whereNotNull('unlocked_at')->latest('unlocked_at')->get(),
            'attempts' => $user->quizAttempts()->with('question')->latest('answered_at')->limit(10)->get(),
            'timezone' => $timezone,
        ]);
    }

    public function submit(
        Request $request,
        FinancialLearningService $learning,
        AchievementService $achievements,
    ): RedirectResponse {
        $data = $request->validate([
            'option' => ['required', 'string', Rule::in(['A', 'B', 'C', 'D'])],
        ]);

        $result = $learning->submitAnswer($request->user(), $data['option'], $achievements);
        $message = $result['already_attempted']
            ? 'You have already completed today’s quiz. Your original result is shown below.'
            : ($result['attempt']->is_correct
                ? 'Correct answer. You earned '.$result['attempt']->points_awarded.' points.'
                : 'Not quite. Review the explanation and try again tomorrow.');

        return redirect()->route('learning.index')->with('success', $message);
    }
}
