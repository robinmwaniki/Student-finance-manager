<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_capture_drafts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source', 20);
            $table->string('merchant', 150)->nullable();
            $table->string('category', 50)->nullable();
            $table->decimal('amount', 14, 2)->nullable();
            $table->date('transaction_date')->nullable();
            $table->string('tag', 50)->nullable();
            $table->string('source_reference', 100)->nullable();
            $table->char('fingerprint', 64);
            $table->string('status', 16)->default('pending');
            $table->uuid('submission_token')->unique();
            $table->foreignId('expense_id')->nullable()->constrained('expenses')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'fingerprint'], 'expense_capture_user_fingerprint_unique');
            $table->index(['user_id', 'status', 'expires_at']);
        });

        Schema::create('expense_capture_webhook_credentials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->char('previous_token_hash', 64)->nullable()->unique();
            $table->text('signing_secret');
            $table->text('previous_signing_secret')->nullable();
            $table->timestamp('previous_expires_at')->nullable();
            $table->timestamp('rotated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('expense_capture_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_id', 120);
            $table->char('payload_hash', 64);
            $table->timestamp('received_at');
            $table->unique(['user_id', 'event_id'], 'expense_capture_webhook_event_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_capture_webhook_events');
        Schema::dropIfExists('expense_capture_webhook_credentials');
        Schema::dropIfExists('expense_capture_drafts');
    }
};
