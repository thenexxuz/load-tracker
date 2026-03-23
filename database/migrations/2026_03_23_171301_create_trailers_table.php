<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('trailers', function (Blueprint $table) {
            $table->id();
            $table->uuid('guid')->unique();
            $table->string('number', 100)->unique(); // Trailer number/ID
            $table->foreignId('carrier_id')->constrained('carriers')->onDelete('restrict');
            $table->string('type', 50)->nullable(); // e.g., 'standard', 'reefer', 'flatbed'
            $table->unsignedInteger('capacity')->nullable(); // Weight capacity in lbs
            $table->string('license_plate', 50)->nullable();
            $table->enum('status', ['available', 'in_use', 'loaned', 'maintenance', 'retired'])->default('available');
            $table->date('purchased_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('carrier_id');
            $table->index('status');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trailers');
    }
};
