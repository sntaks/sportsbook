<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fixtures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->restrictOnDelete();
            $table->string('external_id')->unique();
            $table->string('home_team');
            $table->string('away_team');
            $table->timestamp('kicks_off_at')->index();
            $table->string('status')->default('scheduled');
            $table->timestamp('last_seen_in_feed_at')->nullable();
            $table->timestamps();

            $table->index(['competition_id', 'kicks_off_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fixtures');
    }
};
