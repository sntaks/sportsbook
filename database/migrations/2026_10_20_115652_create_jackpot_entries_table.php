<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A player's jackpot entry: one pick per match. Money columns are cents.
     */
    public function up(): void
    {
        Schema::create('jackpot_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jackpot_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('reference', 12)->unique();
            $table->string('status', 12);
            $table->bigInteger('stake');
            $table->unsignedTinyInteger('correct_count')->nullable();
            $table->bigInteger('prize')->nullable();
            // The entry form's one-time key: a double tap on "Enter" finds the entry already made.
            $table->string('client_key', 64)->nullable();
            $table->foreignId('stake_transaction_id')->nullable()->constrained('wallet_transactions')->restrictOnDelete();
            $table->foreignId('prize_transaction_id')->nullable()->constrained('wallet_transactions')->restrictOnDelete();
            $table->timestamp('placed_at');
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'client_key']);
            $table->index(['user_id', 'placed_at']);
            $table->index(['jackpot_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jackpot_entries');
    }
};
