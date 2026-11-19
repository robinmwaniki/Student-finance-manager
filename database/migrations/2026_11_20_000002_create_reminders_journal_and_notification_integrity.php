<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bill_reminders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 150);
            $table->string('category', 50)->nullable();
            $table->decimal('amount', 14, 2);
            $table->date('due_date');
            $table->string('recurrence', 16)->default('once');
            $table->unsignedTinyInteger('due_day')->nullable();
            $table->unsignedTinyInteger('due_month')->nullable();
            $table->string('status', 16)->default('active');
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();
            $table->index(['user_id', 'status', 'due_date'], 'bill_reminders_user_due_index');
        });

        Schema::create('journal_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('note_month', 7);
            $table->text('content');
            $table->string('mood', 16);
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'note_month'], 'journal_entries_user_month_unique');
        });

        Schema::table('user_notifications', function (Blueprint $table): void {
            $table->string('deduplication_key', 190)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->unique(['user_id', 'deduplication_key'], 'user_notifications_dedup_unique');
        });
    }

    public function down(): void
    {
        Schema::table('user_notifications', function (Blueprint $table): void {
            $table->dropUnique('user_notifications_dedup_unique');
            $table->dropColumn(['deduplication_key', 'read_at']);
        });

        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('bill_reminders');
    }
};
