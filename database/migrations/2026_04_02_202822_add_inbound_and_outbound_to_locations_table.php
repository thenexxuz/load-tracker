<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->boolean('inbound')->default(false)->after('expected_arrival_time');
            $table->boolean('outbound')->default(false)->after('inbound');
        });

        DB::table('locations')
            ->where('type', 'pickup')
            ->update([
                'outbound' => true,
                'updated_at' => now(),
            ]);

        DB::table('locations')
            ->whereIn('type', ['distribution_center', 'recycling'])
            ->update([
                'inbound' => true,
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn(['inbound', 'outbound']);
        });
    }
};
