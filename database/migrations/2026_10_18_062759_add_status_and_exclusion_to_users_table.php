<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin suspension, and the cool-off or self-exclusion a player sets for themselves.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->after('adult_confirmed_at');
            $table->string('suspension_reason')->nullable()->after('status');
            $table->string('exclusion_type', 20)->nullable()->after('suspension_reason');
            $table->timestamp('excluded_until')->nullable()->after('exclusion_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['status', 'suspension_reason', 'exclusion_type', 'excluded_until']);
        });
    }
};
