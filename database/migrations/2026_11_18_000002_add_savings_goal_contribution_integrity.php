<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('savings_goals', function (Blueprint $table): void {
            $table->unsignedInteger('lock_version')->default(0);
        });
        Schema::table('savings', function (Blueprint $table): void {
            // Preserve legacy goal labels; new rows additionally reference a goal ID.
            $table->foreignId('goal_id')->nullable()->after('user_id')->constrained('savings_goals')->nullOnDelete();
            $table->uuid('submission_token')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->unique(['user_id', 'submission_token'], 'savings_user_submission_token_unique');
            $table->index(['user_id', 'goal_id', 'transaction_date'], 'savings_user_goal_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('savings', function (Blueprint $table): void {
            $table->dropIndex('savings_user_goal_date_index');
            $table->dropUnique('savings_user_submission_token_unique');
            $table->dropConstrainedForeignId('goal_id');
            $table->dropColumn(['submission_token', 'lock_version']);
        });
        Schema::table('savings_goals', function (Blueprint $table): void {
            $table->dropColumn('lock_version');
        });
    }
};
