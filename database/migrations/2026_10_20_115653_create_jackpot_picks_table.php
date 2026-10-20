<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One pick per match on a jackpot entry: "1" home win, "X" draw or "2" away win.
     */
    public function up(): void
    {
        Schema::create('jackpot_picks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jackpot_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('jackpot_fixture_id')->constrained()->restrictOnDelete();
            $table->string('pick', 1);

            $table->unique(['jackpot_entry_id', 'jackpot_fixture_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jackpot_picks');
    }
};
