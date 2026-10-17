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
        Schema::create('odds_api_calls', function (Blueprint $table) {
            $table->id();
            $table->string('endpoint');
            $table->string('sport_key')->nullable();
            $table->unsignedInteger('credits_spent')->default(0);
            $table->unsignedInteger('credits_remaining')->nullable();
            $table->unsignedInteger('credits_used')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->boolean('succeeded');
            $table->string('error')->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('odds_api_calls');
    }
};
