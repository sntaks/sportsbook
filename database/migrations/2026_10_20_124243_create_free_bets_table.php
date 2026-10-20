<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Free bets: a stake the player did not pay for, used whole on one bet (decision 41). Amounts are cents. The bet it
     * was used on points back here through bets.free_bet_id.
     */
    public function up(): void
    {
        Schema::create('free_bets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->bigInteger('amount');
            $table->decimal('min_odds', 8, 2);
            $table->timestamp('expires_at');
            $table->string('status', 12);
            $table->string('source', 12);
            $table->string('note')->nullable();
            // The admin who gave it; who voided it is in the activity log.
            $table->foreignId('admin_id')->nullable()->constrained()->restrictOnDelete();
            // A replacement for a free bet whose bet ended void or was cancelled.
            $table->foreignId('replaces_bet_id')->nullable()->constrained('bets')->restrictOnDelete();
            $table->string('void_reason')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('free_bets');
    }
};
