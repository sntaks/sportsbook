<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every M-Pesa payment in: STK pushes we started and Paybill payments players made themselves.
     */
    public function up(): void
    {
        Schema::create('mpesa_deposits', function (Blueprint $table) {
            $table->id();
            // Empty for a Paybill payment whose account number matched no player.
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('channel', 20);
            $table->string('status', 20);
            $table->string('phone', 64);
            $table->bigInteger('amount');
            $table->string('account_reference')->nullable();
            $table->string('merchant_request_id')->nullable();
            $table->string('checkout_request_id')->nullable()->unique();
            $table->string('mpesa_receipt')->nullable()->unique();
            $table->string('result_code')->nullable();
            $table->string('result_description')->nullable();
            $table->json('callback_payload')->nullable();
            $table->foreignId('wallet_transaction_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('query_attempts')->default(0);
            $table->timestamp('last_queried_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'channel', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mpesa_deposits');
    }
};
