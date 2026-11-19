<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_ai_conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 100);
            $table->timestamps();
            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('financial_ai_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained('financial_ai_conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 12);
            $table->text('content');
            $table->boolean('context_shared')->default(false);
            $table->timestamps();
            $table->index(['user_id', 'conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_ai_messages');
        Schema::dropIfExists('financial_ai_conversations');
    }
};
