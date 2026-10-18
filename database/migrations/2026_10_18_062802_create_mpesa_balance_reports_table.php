<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * M-Pesa account balance checks. Safaricom answers on a callback, so each request is a row that the
     * answer fills in, matched by conversation ID.
     */
    public function up(): void
    {
        Schema::create('mpesa_balance_reports', function (Blueprint $table) {
            $table->id();
            $table->string('account', 20);
            $table->string('conversation_id')->nullable()->unique();
            $table->string('originator_conversation_id')->nullable()->unique();
            $table->string('status', 20);
            $table->json('balances')->nullable();
            $table->string('result_description')->nullable();
            $table->foreignId('requested_by_admin_id')->nullable()->constrained('admins')->restrictOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mpesa_balance_reports');
    }
};
