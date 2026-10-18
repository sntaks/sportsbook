<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which price changes the player accepts when placing a bet: any, higher (the default) or none.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('odds_change_policy', 10)->default('higher')->after('excluded_until');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('odds_change_policy');
        });
    }
};
