<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations. Existing competitions are few, so their slugs are backfilled here before the
     * column becomes required.
     */
    public function up(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('odds_api_key');
        });

        DB::table('competitions')->orderBy('id')->each(function (object $competition): void {
            $slug = Str::slug($competition->name);

            if (DB::table('competitions')->where('slug', $slug)->exists()) {
                $slug .= '-'.$competition->id;
            }

            DB::table('competitions')->where('id', $competition->id)->update(['slug' => $slug]);
        });

        Schema::table('competitions', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
