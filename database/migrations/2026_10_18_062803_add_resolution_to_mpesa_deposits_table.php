<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What M-Pesa actually received (for mismatched and unmatched payments) and how an admin settled it.
     */
    public function up(): void
    {
        Schema::table('mpesa_deposits', function (Blueprint $table) {
            $table->bigInteger('paid_amount')->nullable()->after('amount');
            $table->foreignId('resolved_by_admin_id')->nullable()->after('completed_at')->constrained('admins')->restrictOnDelete();
            $table->timestamp('resolved_at')->nullable()->after('resolved_by_admin_id');
            $table->string('resolution_note')->nullable()->after('resolved_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mpesa_deposits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('resolved_by_admin_id');
            $table->dropColumn(['paid_amount', 'resolved_at', 'resolution_note']);
        });
    }
};
