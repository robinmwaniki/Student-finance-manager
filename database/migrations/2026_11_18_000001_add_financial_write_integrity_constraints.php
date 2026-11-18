<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddFinancialWriteIntegrityConstraints extends Migration
{
    public function up(): void
    {
        $hasDuplicateBudgets = DB::table('budgets')
            ->select(['user_id', 'category', 'budget_month'])
            ->groupBy(['user_id', 'category', 'budget_month'])
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicateBudgets) {
            throw new \RuntimeException(
                'Cannot add the monthly budget uniqueness constraint: duplicate user/category/month budgets exist. Reconcile these records before retrying this migration.'
            );
        }

        Schema::table('income', function (Blueprint $table): void {
            $table->uuid('submission_token')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->unique(['user_id', 'submission_token'], 'income_user_submission_token_unique');
        });

        Schema::table('expenses', function (Blueprint $table): void {
            $table->uuid('submission_token')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->unique(['user_id', 'submission_token'], 'expenses_user_submission_token_unique');
        });

        Schema::table('budgets', function (Blueprint $table): void {
            $table->unique(['user_id', 'category', 'budget_month'], 'budgets_user_category_month_unique');
        });
    }

    public function down(): void
    {
        Schema::table('budgets', function (Blueprint $table): void {
            $table->dropUnique('budgets_user_category_month_unique');
        });

        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropUnique('expenses_user_submission_token_unique');
            $table->dropColumn(['submission_token', 'lock_version']);
        });

        Schema::table('income', function (Blueprint $table): void {
            $table->dropUnique('income_user_submission_token_unique');
            $table->dropColumn(['submission_token', 'lock_version']);
        });
    }
}
