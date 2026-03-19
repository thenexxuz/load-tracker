<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            // Drop the unique index if it exists
            if (Schema::hasIndex('locations', 'locations_short_code_unique') ||
                Schema::hasIndex('locations', 'short_code') ||
                Schema::hasIndex('locations', 'short_code_unique')) {
                $table->dropUnique(['short_code']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            // Restore unique constraint if it doesn't exist
            if (!Schema::hasIndex('locations', 'locations_short_code_unique') &&
                !Schema::hasIndex('locations', 'short_code') &&
                !Schema::hasIndex('locations', 'short_code_unique')) {
                $table->unique('short_code');
            }
        });
    }
};