<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->uuid('guid')->unique();
            $table->string('short_code', 20)->unique();
            $table->string('name')->nullable();
            $table->text('address');
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable();
            $table->string('zip', 10)->nullable();
            $table->string('country', 2)->default('US');

            // New: location type as enum
            $table->enum('type', [
                'pickup',
                'distribution_center',
                'recycling',
            ])->default('pickup');

            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->nullableMorphs('recycling_location');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
