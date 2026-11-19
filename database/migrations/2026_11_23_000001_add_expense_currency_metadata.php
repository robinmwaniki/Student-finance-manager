<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->decimal('original_amount', 14, 2)->nullable();
            $table->char('original_currency', 3)->nullable();
            $table->decimal('exchange_rate', 20, 10)->nullable();
            $table->decimal('converted_amount', 14, 2)->nullable();
            $table->timestamp('exchange_rate_updated_at')->nullable();
            $table->string('exchange_rate_source', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropColumn(['original_amount', 'original_currency', 'exchange_rate', 'converted_amount', 'exchange_rate_updated_at', 'exchange_rate_source']);
        });
    }
};
