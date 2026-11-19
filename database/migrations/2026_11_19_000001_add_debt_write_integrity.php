<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('debts', function (Blueprint $table): void {
            // Legacy debt rows remain valid; only new form submissions receive a token.
            $table->uuid('submission_token')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->unique(['user_id', 'submission_token'], 'debts_user_submission_token_unique');
            $table->index(['user_id', 'type', 'due_date'], 'debts_user_type_due_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('debts', function (Blueprint $table): void {
            $table->dropIndex('debts_user_type_due_date_index');
            $table->dropUnique('debts_user_submission_token_unique');
            $table->dropColumn(['submission_token', 'lock_version']);
        });
    }
};
