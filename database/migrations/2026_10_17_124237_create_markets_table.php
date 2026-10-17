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
        Schema::create('markets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixture_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->decimal('line', 5, 2)->nullable();
            $table->string('key');
            $table->string('status')->default('suspended');
            $table->string('suspension_reason')->nullable();
            $table->unsignedSmallInteger('bookmaker_count')->default(0);
            $table->timestamp('priced_at')->nullable();
            $table->timestamps();

            $table->unique(['fixture_id', 'key']);
            $table->index(['status', 'priced_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('markets');
    }
};
