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
        Schema::create('odds_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('selection_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 8, 2);
            $table->decimal('fair_probability', 9, 6)->nullable();
            $table->string('source');
            $table->timestamp('captured_at');

            $table->index(['selection_id', 'captured_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('odds_snapshots');
    }
};
