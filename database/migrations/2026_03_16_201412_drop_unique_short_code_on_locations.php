<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            // Drop the unique index (SQLite calls it by the column name usually)
            $table->dropUnique(['short_code']);
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            // Optional: restore unique constraint on rollback
            $table->unique('short_code');
        });
    }
};