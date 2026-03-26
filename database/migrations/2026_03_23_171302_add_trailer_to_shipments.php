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

        if ($driver === 'sqlite') {
            $shouldAddTrailerId = ! Schema::hasColumn('shipments', 'trailer_id');
            $shouldAddLoanedFromTrailerId = ! Schema::hasColumn('shipments', 'loaned_from_trailer_id');

            if (! $shouldAddTrailerId && ! $shouldAddLoanedFromTrailerId) {
                return;
            }

            Schema::table('shipments', function (Blueprint $table) use ($shouldAddTrailerId, $shouldAddLoanedFromTrailerId) {
                if ($shouldAddTrailerId) {
                    $table->unsignedBigInteger('trailer_id')->nullable()->after('carrier_id');
                }

                if ($shouldAddLoanedFromTrailerId) {
                    $table->unsignedBigInteger('loaned_from_trailer_id')->nullable()->after('trailer_id');
                }
            });

            return;
        }

        if (! Schema::hasColumn('shipments', 'trailer_id')) {
            Schema::table('shipments', function (Blueprint $table) {
                $table->foreignId('trailer_id')->nullable()->constrained('trailers')->nullOnDelete()->after('carrier_id');
            });
        }

        if (! Schema::hasColumn('shipments', 'loaned_from_trailer_id')) {
            Schema::table('shipments', function (Blueprint $table) {
                $table->foreignId('loaned_from_trailer_id')->nullable()->constrained('trailers')->nullOnDelete()->after('trailer_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $columnsToDrop = array_values(array_filter([
                Schema::hasColumn('shipments', 'loaned_from_trailer_id') ? 'loaned_from_trailer_id' : null,
                Schema::hasColumn('shipments', 'trailer_id') ? 'trailer_id' : null,
            ]));

            if ($columnsToDrop === []) {
                return;
            }

            Schema::table('shipments', function (Blueprint $table) use ($columnsToDrop) {
                $table->dropColumn($columnsToDrop);
            });

            return;
        }

        if (Schema::hasColumn('shipments', 'loaned_from_trailer_id')) {
            Schema::table('shipments', function (Blueprint $table) {
                $table->dropConstrainedForeignId('loaned_from_trailer_id');
            });
        }

        if (Schema::hasColumn('shipments', 'trailer_id')) {
            Schema::table('shipments', function (Blueprint $table) {
                $table->dropConstrainedForeignId('trailer_id');
            });
        }
    }
};
