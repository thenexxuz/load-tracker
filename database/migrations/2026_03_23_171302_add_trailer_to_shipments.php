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
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $shouldAddTrailerId = ! Schema::hasColumn('shipments', 'trailer_id');
            $shouldAddLoanedFromTrailerId = ! Schema::hasColumn('shipments', 'loaned_from_trailer_id');

            if (! $shouldAddTrailerId && ! $shouldAddLoanedFromTrailerId) {
                return;
            }

            if ($shouldAddTrailerId) {
                DB::statement('ALTER TABLE shipments ADD COLUMN trailer_id INTEGER');
            }

            if ($shouldAddLoanedFromTrailerId) {
                DB::statement('ALTER TABLE shipments ADD COLUMN loaned_from_trailer_id INTEGER');
            }

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
            if (Schema::hasColumn('shipments', 'loaned_from_trailer_id')) {
                DB::statement('ALTER TABLE shipments DROP COLUMN loaned_from_trailer_id');
            }

            if (Schema::hasColumn('shipments', 'trailer_id')) {
                DB::statement('ALTER TABLE shipments DROP COLUMN trailer_id');
            }

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
