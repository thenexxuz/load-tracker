<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rates', function (Blueprint $table) {
            // Drop unique constraint first (before dropping the column it references)
            if (Schema::hasIndex('rates', 'rates_unique_combination')) {
                $table->dropUnique('rates_unique_combination');
            }

            // Drop old DC relationship if it exists
            if (Schema::hasColumn('rates', 'dc_location_id')) {
                $table->dropForeign(['dc_location_id']);
                $table->dropColumn('dc_location_id');
            }

            // Add new destination fields if they don't exist
            if (!Schema::hasColumn('rates', 'destination_city')) {
                $table->string('destination_city')->nullable();
            }
            if (!Schema::hasColumn('rates', 'destination_state')) {
                $table->string('destination_state', 2)->nullable();
            }
            if (!Schema::hasColumn('rates', 'destination_country')) {
                $table->string('destination_country', 2)->nullable();
            }

            // Create new unique constraint if it doesn't exist
            if (!Schema::hasIndex('rates', 'rates_unique_lane')) {
                $table->unique(
                    ['name', 'carrier_id', 'pickup_location_id', 'destination_city', 'destination_state', 'destination_country'],
                    'rates_unique_lane'
                );
            }
        });
    }

    public function down(): void
    {
        Schema::table('rates', function (Blueprint $table) {
            // Drop new unique constraint if it exists
            if (Schema::hasIndex('rates', 'rates_unique_lane')) {
                $table->dropUnique('rates_unique_lane');
            }

            // Drop new destination fields if they exist
            $columnsToDrop = [];
            if (Schema::hasColumn('rates', 'destination_city')) {
                $columnsToDrop[] = 'destination_city';
            }
            if (Schema::hasColumn('rates', 'destination_state')) {
                $columnsToDrop[] = 'destination_state';
            }
            if (Schema::hasColumn('rates', 'destination_country')) {
                $columnsToDrop[] = 'destination_country';
            }
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }

            // Add back old DC relationship if it doesn't exist
            if (!Schema::hasColumn('rates', 'dc_location_id')) {
                $table->foreignId('dc_location_id')
                    ->constrained('locations')
                    ->cascadeOnDelete();
            }

            // Create old unique constraint if it doesn't exist
            if (!Schema::hasIndex('rates', 'rates_unique_combination')) {
                $table->unique(
                    ['carrier_id', 'pickup_location_id', 'dc_location_id'],
                    'rates_unique_combination'
                );
            }
        });
    }
};
