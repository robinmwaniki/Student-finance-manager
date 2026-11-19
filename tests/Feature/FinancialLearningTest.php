<?php

namespace Tests\Feature;

use App\Models\QuizQuestion;
use App\Models\User;
use App\Services\AchievementService;
use App\Services\FinancialLearningService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialLearningTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_financial_activity_achievements_are_awarded_once_from_persisted_records(): void
    {
        $user = $this->makeUser();
        $user->income()->create([
            'source' => 'Salary',
            'amount' => '1000.00',
            'transaction_date' => '2026-11-10',
        ]);
        $service = app(AchievementService::class);

        $this->assertSame(1, $service->evaluate($user));
        $this->assertSame(0, $service->evaluate($user));
        $this->assertSame(15, (int) $user->fresh()->points);
        $this->assertDatabaseHas('achievements', [
            'user_id' => $user->id,
            'code' => 'first_income',
            'points' => 15,
        ]);
        $this->assertSame(1, $user->achievements()->count());
    }

    public function test_correct_daily_answer_awards_points_and_duplicate_submission_is_idempotent(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-19 12:00:00', 'UTC'));
        $user = $this->makeUser();
        $learning = app(FinancialLearningService::class);
        $question = $learning->questionForDate(CarbonImmutable::now('UTC'));

        $first = $learning->submitAnswer($user, $question->correct_option, app(AchievementService::class));
        $second = $learning->submitAnswer($user, 'D', app(AchievementService::class));

        $this->assertTrue($first['attempt']->is_correct);
        $this->assertFalse($first['already_attempted']);
        $this->assertTrue($second['already_attempted']);
        $this->assertSame($first['attempt']->id, $second['attempt']->id);
        $this->assertSame(20, (int) $user->fresh()->points);
        $this->assertSame(1, $user->quizAttempts()->count());
        $this->assertSame(1, $user->achievements()->where('code', 'first_quiz')->count());
    }

    public function test_wrong_answer_receives_feedback_without_quiz_points(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-19 12:00:00', 'UTC'));
        $user = $this->makeUser();
        $learning = app(FinancialLearningService::class);
        $question = $learning->questionForDate(CarbonImmutable::now('UTC'));
        $wrongOption = $question->correct_option === 'A' ? 'B' : 'A';

        $result = $learning->submitAnswer($user, $wrongOption, app(AchievementService::class));

        $this->assertFalse($result['attempt']->is_correct);
        $this->assertSame(0, $result['attempt']->points_awarded);
        $this->assertSame(10, (int) $user->fresh()->points);
        $this->assertSame($question->explanation, $result['attempt']->question->explanation);
    }

    public function test_streak_increments_on_consecutive_local_days_and_resets_after_a_missed_day(): void
    {
        $user = $this->makeUser();
        $learning = app(FinancialLearningService::class);
        $achievements = app(AchievementService::class);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-17 12:00:00', 'UTC'));
        $dayOne = $learning->submitAnswer($user, 'D', $achievements);
        $this->assertSame(1, $dayOne['streak']->current_streak);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-18 12:00:00', 'UTC'));
        $dayTwo = $learning->submitAnswer($user, 'D', $achievements);
        $this->assertSame(2, $dayTwo['streak']->current_streak);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-20 12:00:00', 'UTC'));
        $afterMissedDay = $learning->submitAnswer($user, 'D', $achievements);

        $this->assertSame(1, $afterMissedDay['streak']->current_streak);
        $this->assertSame(2, $afterMissedDay['streak']->longest_streak);
        $this->assertSame('2026-11-20', $afterMissedDay['streak']->last_activity_date->toDateString());
    }

    public function test_streak_keeps_its_original_timezone_when_application_timezone_changes(): void
    {
        config(['app.timezone' => 'Asia/Tokyo']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-18 15:30:00', 'UTC'));
        $user = $this->makeUser();
        $learning = app(FinancialLearningService::class);
        $achievements = app(AchievementService::class);

        $first = $learning->submitAnswer($user, 'D', $achievements);
        $this->assertSame('2026-11-19', $first['attempt']->quiz_date->toDateString());
        $this->assertSame('Asia/Tokyo', $first['streak']->timezone);

        config(['app.timezone' => 'UTC']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-19 16:00:00', 'UTC'));
        $next = $learning->submitAnswer($user, 'D', $achievements);

        $this->assertSame('2026-11-20', $next['attempt']->quiz_date->toDateString());
        $this->assertSame(2, $next['streak']->current_streak);
        $this->assertSame('Asia/Tokyo', $next['streak']->timezone);
    }

    public function test_daily_quiz_page_shows_question_achievement_points_and_attempt_history(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-19 12:00:00', 'UTC'));
        $user = $this->makeUser();

        $this->actingAs($user)->get(route('learning.index'))
            ->assertOk()
            ->assertSee('Test your money knowledge')
            ->assertSee('Submit answer')
            ->assertSee('Total points')
            ->assertSee('Achievement progress');
    }

    public function test_user_cannot_read_another_users_quiz_attempts_or_awards(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-19 12:00:00', 'UTC'));
        $owner = $this->makeUser();
        $other = $this->makeUser('other-learning@example.test');
        $learning = app(FinancialLearningService::class);
        $question = $learning->questionForDate(CarbonImmutable::now('UTC'));
        $learning->submitAnswer($owner, $question->correct_option, app(AchievementService::class));

        $this->assertSame(1, $owner->quizAttempts()->count());
        $this->assertSame(0, $other->quizAttempts()->count());
        $this->assertSame(1, $owner->achievements()->count());
        $this->assertSame(0, $other->achievements()->count());
    }

    public function test_quiz_question_bank_is_locally_seeded_and_has_valid_options(): void
    {
        $this->assertGreaterThanOrEqual(10, QuizQuestion::query()->where('is_active', true)->count());

        $question = QuizQuestion::query()->firstOrFail();
        $this->assertArrayHasKey($question->correct_option, $question->options);
        $this->assertCount(4, $question->options);
    }

    public function test_quiz_submission_requires_a_valid_option_and_authentication(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->post(route('learning.quiz.submit'), ['option' => 'Z'])
            ->assertSessionHasErrors('option');
        $this->app['auth']->forgetGuards();
        $this->post(route('learning.quiz.submit'), ['option' => 'A'])
            ->assertRedirect(route('login'));
    }


    public function test_quiz_and_streak_achievement_thresholds_award_bonus_points_once(): void
    {
        $user = $this->makeUser();
        $question = QuizQuestion::query()->firstOrFail();

        for ($day = 0; $day < 25; $day++) {
            $date = CarbonImmutable::parse('2026-01-01')->addDays($day);
            $user->quizAttempts()->create([
                'question_id' => $question->id,
                'quiz_date' => $date->toDateString(),
                'selected_option' => $question->correct_option,
                'is_correct' => true,
                'points_awarded' => 10,
                'answered_at' => $date->setTime(12, 0),
            ]);
        }

        $user->financialStreak()->create([
            'current_streak' => 7,
            'longest_streak' => 7,
            'last_activity_date' => '2026-01-25',
            'timezone' => 'UTC',
        ]);

        $service = app(AchievementService::class);

        $this->assertSame(5, $service->evaluate($user));
        $this->assertSame(0, $service->evaluate($user));
        $this->assertSame(170, (int) $user->fresh()->points);
        $this->assertSame(5, $user->achievements()->count());

        $progress = collect($service->progress($user))->keyBy('code');
        $this->assertTrue($progress['quiz_correct_10']['unlocked']);
        $this->assertTrue($progress['quiz_correct_25']['unlocked']);
        $this->assertTrue($progress['streak_7']['unlocked']);
    }

    private function makeUser(string $email = 'learning@example.test'): User
    {
        static $counter = 0;
        $counter++;

        return User::create([
            'fullname' => 'Learning User',
            'email' => $counter === 1 ? $email : str_replace('@', $counter.'@', $email),
            'password' => 'StrongPassword123',
        ]);
    }
}
