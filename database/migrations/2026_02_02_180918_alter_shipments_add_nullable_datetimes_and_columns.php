<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            // Change existing boolean columns to nullable datetime
            $table->dateTime('on_site')->nullable()->change();
            $table->dateTime('shipped')->nullable()->change();
            $table->dateTime('recycling_sent')->nullable()->change();
            $table->dateTime('paperwork_sent')->nullable()->change();
            $table->dateTime('delivery_alert_sent')->nullable()->change();

            // Add new columns
            $table->dateTime('crossed')->nullable()->after('shipped');
            $table->string('seal_number')->nullable()->after('crossed');
            $table->string('drivers_id')->nullable()->after('seal_number');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            // Revert new columns
            $table->dropColumn(['crossed', 'seal_number', 'drivers_id']);

            // Revert datetime columns back to boolean (only if needed - be careful with data loss!)
            $table->boolean('on_site')->nullable(false)->default(false)->change();
            $table->boolean('shipped')->nullable(false)->default(false)->change();
            $table->boolean('recycling_sent')->nullable(false)->default(false)->change();
            $table->boolean('paperwork_sent')->nullable(false)->default(false)->change();
            $table->boolean('delivery_alert_sent')->nullable(false)->default(false)->change();
        });
    }
};
