<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Why a leg was voided on its own (postponed match, pricing error), as opposed to by the match result. Such a
     * leg stays void when the match result is corrected.
     */
    public function up(): void
    {
        Schema::table('bet_legs', function (Blueprint $table) {
            $table->string('void_reason')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bet_legs', function (Blueprint $table) {
            $table->dropColumn('void_reason');
        });
    }
};
