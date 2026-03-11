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
            $table->string('name')->nullable()->after('id');

            $table->enum('type', ['flat', 'per_mile'])
                ->default('per_mile')
                ->after('name');
            // 'flat' = fixed amount regardless of distance
            // 'per_mile' = rate × total_miles
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rates', function (Blueprint $table) {
            $table->dropColumn(['name', 'type']);
        });
    }
};