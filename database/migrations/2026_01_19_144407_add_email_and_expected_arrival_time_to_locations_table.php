<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->string('email')->nullable()->after('country'); // Email field (nullable)
            $table->dateTime('expected_arrival_time')->nullable()->after('email'); // Expected arrival time (datetime, nullable)
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn(['email', 'expected_arrival_time']);
        });
    }
};
