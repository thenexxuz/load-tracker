<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rates', function (Blueprint $table) {
            $table->dropForeign(['carrier_id']);
            $table->dropForeign(['pickup_location_id']);
        });

        Schema::table('rates', function (Blueprint $table) {
            $table->unsignedBigInteger('carrier_id')->nullable()->change();
            $table->unsignedBigInteger('pickup_location_id')->nullable()->change();
            $table->string('destination_city')->nullable()->change();
            $table->string('destination_state', 2)->nullable()->change();
            $table->string('destination_country', 2)->nullable()->change();
        });

        Schema::table('rates', function (Blueprint $table) {
            $table->foreign('carrier_id')->references('id')->on('carriers')->nullOnDelete();
            $table->foreign('pickup_location_id')->references('id')->on('locations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rates', function (Blueprint $table) {
            $table->dropForeign(['carrier_id']);
            $table->dropForeign(['pickup_location_id']);
        });

        Schema::table('rates', function (Blueprint $table) {
            $table->unsignedBigInteger('carrier_id')->nullable(false)->change();
            $table->unsignedBigInteger('pickup_location_id')->nullable(false)->change();
            $table->string('destination_city', 255)->nullable(false)->change();
            $table->string('destination_state', 2)->nullable(false)->change();
            $table->string('destination_country', 2)->nullable(false)->change();
        });

        Schema::table('rates', function (Blueprint $table) {
            $table->foreign('carrier_id')->references('id')->on('carriers')->cascadeOnDelete();
            $table->foreign('pickup_location_id')->references('id')->on('locations')->cascadeOnDelete();
        });
    }
};
