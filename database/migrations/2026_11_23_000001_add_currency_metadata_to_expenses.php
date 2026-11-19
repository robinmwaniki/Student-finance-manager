<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->decimal('original_amount', 14, 2)->nullable()->after('amount');
            $table->char('original_currency', 3)->default('KES')->after('original_amount');
            $table->decimal('exchange_rate', 18, 8)->default(1)->after('original_currency');
            $table->string('exchange_rate_source', 100)->nullable()->after('exchange_rate');
            $table->timestamp('exchange_rate_at')->nullable()->after('exchange_rate_source');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropColumn([
                'original_amount',
                'original_currency',
                'exchange_rate',
                'exchange_rate_source',
                'exchange_rate_at',
            ]);
        });
    }
};
