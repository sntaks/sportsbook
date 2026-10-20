<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The reports total the ledger by entry type over a date range, and bets by when they were placed.
     */
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->index(['type', 'created_at']);
        });

        Schema::table('bets', function (Blueprint $table) {
            $table->index('placed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropIndex(['type', 'created_at']);
        });

        Schema::table('bets', function (Blueprint $table) {
            $table->dropIndex(['placed_at']);
        });
    }
};
