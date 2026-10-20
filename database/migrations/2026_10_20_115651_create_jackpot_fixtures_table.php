<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The matches in a jackpot, numbered in kick-off order, each with its outcome once known.
     */
    public function up(): void
    {
        Schema::create('jackpot_fixtures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jackpot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fixture_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('position');
            $table->string('fixture_name');
            // The kick-off when the jackpot was published: a match moved more than postponement_void_hours from it is void.
            $table->timestamp('kicks_off_at');
            // "1", "X" or "2" once the full-time result is in.
            $table->string('outcome', 1)->nullable();
            $table->boolean('is_void')->default(false);
            $table->string('void_reason')->nullable();
            $table->timestamps();

            $table->unique(['jackpot_id', 'fixture_id']);
            $table->unique(['jackpot_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jackpot_fixtures');
    }
};
