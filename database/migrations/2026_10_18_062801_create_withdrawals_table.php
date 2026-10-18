<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payouts to players' M-Pesa (B2C). The money is held in the ledger from the moment of the request.
     */
    public function up(): void
    {
        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('status', 20);
            $table->string('phone', 12);
            $table->bigInteger('amount');
            $table->foreignId('hold_transaction_id')->nullable()->constrained('wallet_transactions')->restrictOnDelete();
            $table->foreignId('refund_transaction_id')->nullable()->constrained('wallet_transactions')->restrictOnDelete();
            $table->string('conversation_id')->nullable()->unique();
            $table->string('originator_conversation_id')->nullable()->unique();
            $table->string('status_query_conversation_id')->nullable()->unique();
            $table->string('mpesa_receipt')->nullable()->unique();
            $table->string('result_code')->nullable();
            $table->string('result_description')->nullable();
            $table->json('result_payload')->nullable();
            $table->foreignId('reviewed_by_admin_id')->nullable()->constrained('admins')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
    }
};
