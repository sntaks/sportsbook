<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. The offered price before its most recent change, for the odds-movement arrows.
     */
    public function up(): void
    {
        Schema::table('selections', function (Blueprint $table) {
            $table->decimal('previous_price', 8, 2)->nullable()->after('override_by_admin_id');
            $table->timestamp('price_changed_at')->nullable()->after('previous_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('selections', function (Blueprint $table) {
            $table->dropColumn(['previous_price', 'price_changed_at']);
        });
    }
};
