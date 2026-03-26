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
        $driver = Schema::getConnection()->getDriverName();

        if (Schema::hasColumn('trailers', 'current_location_id')) {
            return;
        }

        Schema::table('trailers', function (Blueprint $table) use ($driver) {
            if ($driver === 'sqlite') {
                $table->unsignedBigInteger('current_location_id')->nullable()->after('carrier_id');

                return;
            }

            $table->foreignId('current_location_id')->nullable()->after('carrier_id')->constrained('locations')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (! Schema::hasColumn('trailers', 'current_location_id')) {
            return;
        }

        Schema::table('trailers', function (Blueprint $table) use ($driver) {
            if ($driver === 'sqlite') {
                $table->dropColumn('current_location_id');

                return;
            }

            $table->dropConstrainedForeignId('current_location_id');
        });
    }
};
