<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('achievements', function (Blueprint $table): void {
            $table->string('code', 64)->nullable()->after('user_id');
            $table->unique(['user_id', 'code'], 'achievements_user_code_unique');
        });

        Schema::create('quiz_questions', function (Blueprint $table): void {
            $table->id();
            $table->string('topic', 60);
            $table->string('question', 500);
            $table->json('options');
            $table->char('correct_option', 1);
            $table->text('explanation');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'topic']);
        });

        Schema::create('quiz_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('quiz_questions')->cascadeOnDelete();
            $table->date('quiz_date');
            $table->char('selected_option', 1);
            $table->boolean('is_correct');
            $table->unsignedSmallInteger('points_awarded')->default(0);
            $table->timestamp('answered_at');
            $table->timestamps();
            $table->unique(['user_id', 'quiz_date'], 'quiz_attempts_user_day_unique');
            $table->index(['user_id', 'answered_at']);
        });

        Schema::create('user_financial_streaks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('current_streak')->default(0);
            $table->unsignedInteger('longest_streak')->default(0);
            $table->date('last_activity_date')->nullable();
            $table->string('timezone', 64)->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();
        });

        DB::table('quiz_questions')->insert([
            [
                'topic' => 'budgeting',
                'question' => 'What is the main purpose of a monthly budget?',
                'options' => json_encode(['A' => 'To plan income and spending', 'B' => 'To guarantee a higher salary', 'C' => 'To avoid recording expenses', 'D' => 'To spend all available money']),
                'correct_option' => 'A',
                'explanation' => 'A budget gives you a plan for allocating income to needs, wants, savings and financial goals.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'topic' => 'saving',
                'question' => 'Which approach is most useful for building an emergency fund?',
                'options' => json_encode(['A' => 'Save only when money is left by chance', 'B' => 'Set a realistic target and save consistently', 'C' => 'Borrow for every unexpected cost', 'D' => 'Invest all cash in one risky asset']),
                'correct_option' => 'B',
                'explanation' => 'A realistic, consistent contribution builds a cash reserve for unexpected expenses.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'topic' => 'debt',
                'question' => 'What does the interest rate on a loan represent?',
                'options' => json_encode(['A' => 'The lender fee for borrowing money, expressed as a rate', 'B' => 'The amount of your monthly income', 'C' => 'A guaranteed discount on the principal', 'D' => 'The loan repayment date']),
                'correct_option' => 'A',
                'explanation' => 'Interest is the cost of borrowing, usually expressed as a percentage of the outstanding balance over time.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'topic' => 'spending',
                'question' => 'What is a practical first step when trying to reduce unnecessary spending?',
                'options' => json_encode(['A' => 'Stop tracking purchases', 'B' => 'Review spending records and identify patterns', 'C' => 'Ignore small purchases', 'D' => 'Increase every spending limit']),
                'correct_option' => 'B',
                'explanation' => 'Reviewing actual transactions reveals recurring costs and categories where you can make informed changes.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'topic' => 'saving',
                'question' => 'Why is it helpful to save before spending discretionary income?',
                'options' => json_encode(['A' => 'It makes saving a planned priority', 'B' => 'It removes all financial risk', 'C' => 'It guarantees investment returns', 'D' => 'It eliminates the need for a budget']),
                'correct_option' => 'A',
                'explanation' => 'Paying yourself first means allocating a planned amount to savings before optional spending.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'topic' => 'credit',
                'question' => 'What is one risk of paying only the minimum amount due on a credit balance?',
                'options' => json_encode(['A' => 'The balance may take longer to repay and accrue more interest', 'B' => 'The debt disappears immediately', 'C' => 'The interest rate must become zero', 'D' => 'Your original purchases are refunded']),
                'correct_option' => 'A',
                'explanation' => 'Minimum payments can leave a large principal balance outstanding, increasing the total interest paid.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'topic' => 'planning',
                'question' => 'Which is the clearest example of a SMART financial goal?',
                'options' => json_encode(['A' => 'Save more someday', 'B' => 'Become wealthy quickly', 'C' => 'Save KES 12,000 over six months by setting aside KES 2,000 monthly', 'D' => 'Spend less if possible']),
                'correct_option' => 'C',
                'explanation' => 'A SMART goal is specific, measurable, achievable, relevant and time-bound.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'topic' => 'banking',
                'question' => 'Why should you compare the fees on financial accounts?',
                'options' => json_encode(['A' => 'Fees can reduce the money you keep over time', 'B' => 'All accounts have identical fees', 'C' => 'Fees increase your savings balance', 'D' => 'It guarantees credit approval']),
                'correct_option' => 'A',
                'explanation' => 'Account fees can accumulate, so comparing them helps you understand the real cost of a financial product.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'topic' => 'investing',
                'question' => 'What does diversification aim to do?',
                'options' => json_encode(['A' => 'Guarantee a profit every month', 'B' => 'Spread exposure across different investments', 'C' => 'Remove all investment risk', 'D' => 'Concentrate all funds in one company']),
                'correct_option' => 'B',
                'explanation' => 'Diversification spreads exposure, which can reduce the impact of a poor result in one investment. It does not remove risk.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'topic' => 'security',
                'question' => 'What should you do if you receive an unexpected message asking for your banking PIN?',
                'options' => json_encode(['A' => 'Reply with the PIN to verify your account', 'B' => 'Share it only if the message has a logo', 'C' => 'Do not share it; contact the institution through an official channel', 'D' => 'Post it in a group for advice']),
                'correct_option' => 'C',
                'explanation' => 'Never share your PIN or one-time password. Verify suspicious requests using the institution’s official contact details.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('user_financial_streaks');
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('quiz_questions');

        Schema::table('achievements', function (Blueprint $table): void {
            $table->dropUnique('achievements_user_code_unique');
            $table->dropColumn('code');
        });
    }
};
