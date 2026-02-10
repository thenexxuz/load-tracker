<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carrier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pickup_location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignId('dc_location_id')->constrained('locations')->cascadeOnDelete();
            $table->decimal('rate', 10, 2);
            $table->timestamps();

            $table->unique(['carrier_id', 'pickup_location_id', 'dc_location_id'], 'rates_unique_combination');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rates');
    }
};
