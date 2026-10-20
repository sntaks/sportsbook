<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What was decided for each invited player once they qualified (decision 44): the referrer got a free bet, was
     * over the monthly limit, or could not bet at the time. One row per invited player, so nobody is rewarded twice.
     */
    public function up(): void
    {
        Schema::create('referral_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('referred_id')->unique()->constrained('users')->restrictOnDelete();
            $table->string('status', 12);
            $table->foreignId('free_bet_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->index(['referrer_id', 'status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_rewards');
    }
};
