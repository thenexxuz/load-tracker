<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rates', function (Blueprint $table) {
            $table->timestamp('effective_from')->nullable()->after('type');
            // Date/time when this rate becomes active (can be null = always active from creation)

            $table->timestamp('effective_to')->nullable()->after('effective_from');
            // Date/time when this rate expires (can be null = no expiration)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rates', function (Blueprint $table) {
            $table->dropColumn(['effective_from', 'effective_to']);
        });
    }
};