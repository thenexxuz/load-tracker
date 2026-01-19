<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->uuid('guid')->unique(); // Optional: unique identifier

            // Core identifiers
            $table->string('shipment_number')->unique();
            $table->string('bol')->nullable(); // Bill of Lading
            $table->string('po_number')->nullable(); // Purchase Order

            // Status (enum or string - using string for flexibility)
            $table->string('status')->default('pending'); // e.g. pending, picked_up, in_transit, delivered, cancelled

            // Locations (foreign keys to your Location model)
            $table->foreignId('shipper_location_id')
                ->constrained('locations')
                ->onDelete('restrict'); // Prevent deletion if used

            $table->foreignId('dc_location_id')
                ->nullable()
                ->constrained('locations')
                ->onDelete('restrict');

            // Carrier
            $table->foreignId('carrier_id')
                ->nullable()
                ->constrained('carriers')
                ->onDelete('set null');

            // Dates & Times
            $table->date('drop_date')->nullable();
            $table->dateTime('pickup_date')->nullable();
            $table->dateTime('delivery_date')->nullable();

            // Quantities
            $table->unsignedInteger('rack_qty')->default(0);
            $table->unsignedInteger('load_bar_qty')->default(0);
            $table->unsignedInteger('strap_qty')->default(0);

            // Trailer / Equipment
            $table->string('trailer')->nullable();

            // Drayage (yes/no or string?)
            $table->boolean('drayage')->default(false);

            // Flags
            $table->boolean('on_site')->default(false);
            $table->boolean('shipped')->default(false);
            $table->boolean('recycling_sent')->default(false);
            $table->boolean('paperwork_sent')->default(false);
            $table->boolean('delivery_alert_sent')->default(false);

            // Timestamps
            $table->timestamps();
            $table->softDeletes(); // Optional: allow soft deletion
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
