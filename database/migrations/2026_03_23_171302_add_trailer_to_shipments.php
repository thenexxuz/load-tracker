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
        Schema::table('shipments', function (Blueprint $table) {
            // Add trailer_id to replace the string 'trailer' field (only if it doesn't exist)
            if (!Schema::hasColumn('shipments', 'trailer_id')) {
                $table->foreignId('trailer_id')->nullable()->constrained('trailers')->nullOnDelete()->after('carrier_id');
                $table->index('trailer_id');
            }

            // Track when a trailer is loaned from another carrier (only if it doesn't exist)
            if (!Schema::hasColumn('shipments', 'loaned_from_trailer_id')) {
                $table->foreignId('loaned_from_trailer_id')->nullable()->constrained('trailers')->nullOnDelete()->after('trailer_id');
                $table->index('loaned_from_trailer_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropForeignKeyIfExists(['trailer_id']);
            $table->dropForeignKeyIfExists(['loaned_from_trailer_id']);
            $table->dropIndex(['trailer_id']);
            $table->dropIndex(['loaned_from_trailer_id']);
            $table->dropColumn(['trailer_id', 'loaned_from_trailer_id']);
        });
    }
};
