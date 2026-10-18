<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The result bets on a fixture settle on: the full-time score (from the feed or an admin), the half-time score
     * when an admin enters it, or void for a match that was abandoned or never played.
     */
    public function up(): void
    {
        Schema::create('fixture_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixture_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('home_score')->nullable();
            $table->unsignedSmallInteger('away_score')->nullable();
            $table->unsignedSmallInteger('half_time_home_score')->nullable();
            $table->unsignedSmallInteger('half_time_away_score')->nullable();
            $table->boolean('is_void')->default(false);
            $table->string('source', 10);
            $table->foreignId('recorded_by_admin_id')->nullable()->constrained('admins')->restrictOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fixture_results');
    }
};
