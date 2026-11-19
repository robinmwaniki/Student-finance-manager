<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->char('mpesa_webhook_token_hash', 64)->nullable()->unique();
        });

        Schema::create('capture_drafts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source', 24);
            $table->string('external_reference', 100)->nullable();
            $table->string('merchant', 120)->nullable();
            $table->decimal('amount', 14, 2);
            $table->date('transaction_date');
            $table->string('category', 50)->nullable();
            $table->uuid('idempotency_key');
            $table->string('status', 16)->default('pending');
            $table->foreignId('confirmed_expense_id')->nullable()->constrained('expenses')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'idempotency_key'], 'capture_drafts_user_idempotency_unique');
            $table->unique(['user_id', 'external_reference'], 'capture_drafts_user_reference_unique');
            $table->index(['user_id', 'status', 'created_at']);
            $table->index('expires_at');
        });

        Schema::create('mpesa_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('received_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mpesa_webhook_events');
        Schema::dropIfExists('capture_drafts');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['mpesa_webhook_token_hash']);
            $table->dropColumn('mpesa_webhook_token_hash');
        });
    }
};
