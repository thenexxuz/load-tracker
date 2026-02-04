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
        Schema::create('location_distances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dc_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignId('recycling_id')->constrained('locations')->cascadeOnDelete();
            $table->decimal('distance_km', 10, 2)->nullable();
            $table->decimal('distance_miles', 10, 2)->nullable();
            $table->string('duration_text')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->json('route_coords')->nullable(); // store for map view
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->unique(['dc_id', 'recycling_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('location_distances');
    }
};
