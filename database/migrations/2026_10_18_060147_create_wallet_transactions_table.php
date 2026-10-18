<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The ledger. Append-only: every change to a balance is one row here, never an update.
     */
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->restrictOnDelete();
            $table->string('type', 40);
            $table->bigInteger('amount');
            $table->bigInteger('balance_after');
            $table->nullableMorphs('source');
            $table->string('idempotency_key')->unique();
            $table->string('description')->nullable();
            $table->foreignId('admin_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['wallet_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
