<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budgets', function (Blueprint $table): void {
            $table->uuid('submission_token')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->unique(['user_id', 'submission_token'], 'budgets_user_submission_token_unique');
        });
    }

    public function down(): void
    {
        Schema::table('budgets', function (Blueprint $table): void {
            $table->dropUnique('budgets_user_submission_token_unique');
            $table->dropColumn(['submission_token', 'lock_version']);
        });
    }
};
