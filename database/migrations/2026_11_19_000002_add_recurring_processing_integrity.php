<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recurring_log', function (Blueprint $table): void {
            $table->string('period_key', 16)->nullable();
            $table->foreignId('income_id')->nullable()->constrained('income')->nullOnDelete();
            $table->foreignId('expense_id')->nullable()->constrained('expenses')->nullOnDelete();
        });

        // Preserve the legacy monthly log history while moving uniqueness to a
        // frequency-neutral key that can also represent daily and ISO-week periods.
        DB::table('recurring_log')->whereNull('period_key')->update([
            'period_key' => DB::raw("CONCAT('M:', applied_month)"),
        ]);

        Schema::table('recurring_log', function (Blueprint $table): void {
            $table->dropUnique(['recurring_id', 'applied_month']);
            $table->unique(['recurring_id', 'period_key'], 'recurring_log_period_unique');
            $table->index(['user_id', 'period_key'], 'recurring_log_user_period_index');
        });
    }

    public function down(): void
    {
        Schema::table('recurring_log', function (Blueprint $table): void {
            $table->dropIndex('recurring_log_user_period_index');
            $table->dropUnique('recurring_log_period_unique');
            $table->unique(['recurring_id', 'applied_month']);
            $table->dropConstrainedForeignId('income_id');
            $table->dropConstrainedForeignId('expense_id');
            $table->dropColumn('period_key');
        });
    }
};
