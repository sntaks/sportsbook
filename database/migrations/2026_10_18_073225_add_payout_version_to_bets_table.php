<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Counts settlements: a corrected result reverses the payout and settles again under the next version, so each
     * payout and reversal has its own ledger key.
     */
    public function up(): void
    {
        Schema::table('bets', function (Blueprint $table) {
            $table->unsignedSmallInteger('settlement_version')->default(0)->after('payout_transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bets', function (Blueprint $table) {
            $table->dropColumn('settlement_version');
        });
    }
};
