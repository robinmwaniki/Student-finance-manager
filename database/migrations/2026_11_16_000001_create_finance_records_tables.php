<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('income', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('source', 100);
            $table->date('transaction_date');
            $table->string('tag', 50)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'transaction_date']);
        });

        Schema::create('expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category', 50);
            $table->decimal('amount', 14, 2);
            $table->date('transaction_date');
            $table->string('tag', 50)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'transaction_date']);
            $table->index(['user_id', 'category']);
        });

        Schema::create('budgets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category', 50);
            $table->decimal('amount', 14, 2);
            $table->decimal('limit_amount', 14, 2)->nullable();
            $table->char('budget_month', 7);
            $table->date('date')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'budget_month']);
        });

        Schema::create('savings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('goal', 100);
            $table->decimal('amount', 14, 2);
            $table->date('transaction_date');
            $table->char('savings_month', 7);
            $table->timestamps();
            $table->index(['user_id', 'transaction_date']);
        });

        Schema::create('debts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('description')->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('type', 16);
            $table->date('due_date')->nullable();
            $table->string('status', 16)->default('pending');
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('savings_goals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 100);
            $table->decimal('target_amount', 14, 2)->nullable();
            $table->decimal('current_amount', 14, 2)->default(0);
            $table->date('due_date')->nullable();
            $table->string('status', 16)->default('active');
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('achievements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 100)->nullable();
            $table->string('description', 255)->nullable();
            $table->unsignedInteger('points')->default(0);
            $table->timestamp('unlocked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('user_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('message');
            $table->string('status', 16)->default('unread');
            $table->string('type', 50)->default('general');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->index(['user_id', 'status', 'created_at']);
        });

        Schema::create('recurring_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category', 50)->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('type', 16);
            $table->string('frequency', 16);
            $table->date('next_due')->nullable();
            $table->string('status', 16)->default('active');
            $table->string('label', 150)->nullable();
            $table->unsignedSmallInteger('day_of_month')->default(1);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['user_id', 'active', 'next_due']);
        });

        Schema::create('recurring_log', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recurring_id')->constrained('recurring_transactions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('applied_month', 7);
            $table->timestamp('applied_at')->useCurrent();
            $table->unique(['recurring_id', 'applied_month']);
            $table->index(['user_id', 'applied_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_log');
        Schema::dropIfExists('recurring_transactions');
        Schema::dropIfExists('user_notifications');
        Schema::dropIfExists('achievements');
        Schema::dropIfExists('savings_goals');
        Schema::dropIfExists('debts');
        Schema::dropIfExists('savings');
        Schema::dropIfExists('budgets');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('income');
    }
};
