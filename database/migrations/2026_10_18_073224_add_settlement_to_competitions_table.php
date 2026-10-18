<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether results come from the scores feed automatically, and when the feed was last asked (it costs credits).
     * The Champions League is settled by hand until it is known whether its scores include extra time.
     */
    public function up(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->boolean('auto_settle')->default(true)->after('is_synced');
            $table->timestamp('scores_checked_at')->nullable()->after('auto_settle');
        });

        DB::table('competitions')->where('odds_api_key', 'soccer_uefa_champs_league')->update(['auto_settle' => false]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->dropColumn(['auto_settle', 'scores_checked_at']);
        });
    }
};
