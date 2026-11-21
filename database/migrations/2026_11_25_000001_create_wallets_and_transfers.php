<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('type', 20);
            $table->char('currency', 3)->default('KES');
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'name']);
            $table->index(['user_id', 'is_default']);
        });

        Schema::table('income', function (Blueprint $table): void {
            $table->foreignId('wallet_id')->nullable()->constrained('wallets')->restrictOnDelete();
            $table->index(['user_id', 'wallet_id', 'transaction_date'], 'income_wallet_date_index');
        });

        Schema::table('expenses', function (Blueprint $table): void {
            $table->foreignId('wallet_id')->nullable()->constrained('wallets')->restrictOnDelete();
            $table->index(['user_id', 'wallet_id', 'transaction_date'], 'expenses_wallet_date_index');
        });

        Schema::create('wallet_transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_wallet_id')->constrained('wallets')->restrictOnDelete();
            $table->foreignId('to_wallet_id')->constrained('wallets')->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('transfer_date');
            $table->string('note', 255)->nullable();
            $table->uuid('submission_token');
            $table->timestamps();
            $table->unique(['user_id', 'submission_token'], 'wallet_transfers_user_token_unique');
            $table->index(['user_id', 'transfer_date']);
        });

        // Give every existing account a default KES wallet and preserve its current history.
        foreach (DB::table('users')->orderBy('id')->cursor() as $user) {
            $walletId = DB::table('wallets')->insertGetId([
                'user_id' => $user->id,
                'name' => 'Main wallet',
                'type' => 'cash',
                'currency' => 'KES',
                'opening_balance' => 0,
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('income')->where('user_id', $user->id)->whereNull('wallet_id')->update(['wallet_id' => $walletId]);
            DB::table('expenses')->where('user_id', $user->id)->whereNull('wallet_id')->update(['wallet_id' => $walletId]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transfers');

        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropIndex('expenses_wallet_date_index');
            $table->dropConstrainedForeignId('wallet_id');
        });

        Schema::table('income', function (Blueprint $table): void {
            $table->dropIndex('income_wallet_date_index');
            $table->dropConstrainedForeignId('wallet_id');
        });

        Schema::dropIfExists('wallets');
    }
};
