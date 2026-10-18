<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A player's own deposit limits, one per rolling period. A raise or removal waits as the pending value until
     * pending_from; a cut applies at once.
     */
    public function up(): void
    {
        Schema::create('deposit_limits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('period', 20);
            // Cents. Null means no limit.
            $table->bigInteger('amount')->nullable();
            $table->bigInteger('pending_amount')->nullable();
            $table->boolean('has_pending_change')->default(false);
            $table->timestamp('pending_from')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'period']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deposit_limits');
    }
};
