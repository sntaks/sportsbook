<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A placed bet: one slip, a single or a multi. Money columns are cents.
     */
    public function up(): void
    {
        Schema::create('bets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('reference', 12)->unique();
            $table->string('type', 10);
            $table->string('status', 12);
            $table->bigInteger('stake');
            $table->decimal('total_odds', 14, 2);
            $table->bigInteger('potential_payout');
            $table->bigInteger('payout')->nullable();
            $table->string('odds_change_policy', 10);
            // The slip's one-time key: a double tap on "Place bet" finds the bet already placed.
            $table->string('client_key', 64)->nullable();
            $table->foreignId('stake_transaction_id')->nullable()->constrained('wallet_transactions')->restrictOnDelete();
            $table->foreignId('payout_transaction_id')->nullable()->constrained('wallet_transactions')->restrictOnDelete();
            $table->timestamp('placed_at');
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'client_key']);
            $table->index(['user_id', 'placed_at']);
            $table->index(['status', 'placed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bets');
    }
};
