<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One pick on a bet. The match, market, pick and price are copied as they were when the bet was placed, so the
     * bet reads (and settles) the same however the market changes afterwards.
     */
    public function up(): void
    {
        Schema::create('bet_legs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('selection_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('market_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('fixture_id')->constrained()->restrictOnDelete();
            $table->string('fixture_name');
            $table->timestamp('kicks_off_at');
            $table->string('market_type', 20);
            $table->decimal('market_line', 5, 2)->nullable();
            $table->string('market_name');
            $table->string('outcome', 20);
            $table->string('selection_name');
            $table->decimal('price', 8, 2);
            $table->string('status', 10);
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->index(['fixture_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bet_legs');
    }
};
