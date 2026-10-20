<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A jackpot: predict the result of every match for a fixed entry price. Money columns are cents.
     */
    public function up(): void
    {
        Schema::create('jackpots', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('status', 12);
            $table->bigInteger('entry_price');
            // [{correct, pool}]: each tier's pool (cents) is split equally between the entries with exactly that many right.
            $table->json('prize_tiers');
            // More void matches than this cancels the jackpot and refunds every entry.
            $table->unsignedTinyInteger('void_limit');
            $table->unsignedInteger('settlement_version')->default(1);
            $table->string('cancel_reason')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'published_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jackpots');
    }
};
