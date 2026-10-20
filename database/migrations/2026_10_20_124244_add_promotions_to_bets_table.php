<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A bet placed with a free bet (unique: a free bet is used once), the multibet bonus it qualified for when placed,
     * and the bonus paid when it settled, in cents.
     */
    public function up(): void
    {
        Schema::table('bets', function (Blueprint $table) {
            $table->foreignId('free_bet_id')->nullable()->unique()->after('user_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('bonus_percent')->default(0)->after('potential_payout');
            $table->bigInteger('bonus')->nullable()->after('payout');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('free_bet_id');
            $table->dropColumn(['bonus_percent', 'bonus']);
        });
    }
};
