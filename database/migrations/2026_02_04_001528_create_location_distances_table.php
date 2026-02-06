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

            // Generic from/to locations (can be any type: DC, recycling, pickup, etc.)
            $table->foreignId('from_location_id')
                ->constrained('locations')
                ->onDelete('cascade');

            $table->foreignId('to_location_id')
                ->constrained('locations')
                ->onDelete('cascade');

            // Distance values
            $table->decimal('distance_km', 10, 2)->nullable();
            $table->decimal('distance_miles', 10, 2)->nullable();

            // Duration (human-readable and numeric)
            $table->string('duration_text')->nullable();     // e.g. "1 hr 23 min"
            $table->integer('duration_minutes')->nullable(); // total minutes for calculations

            // Route geometry for map display
            $table->json('route_coords')->nullable();        // array of [lng, lat] points

            // When this distance was last calculated/verified
            $table->timestamp('calculated_at')->nullable();

            // Timestamps
            $table->timestamps();

            // Ensure no duplicate pairs (bidirectional is handled in model/query)
            $table->unique(['from_location_id', 'to_location_id']);
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