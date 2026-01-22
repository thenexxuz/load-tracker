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
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();

            $table->uuid('guid')->unique();

            $table->string('shipment_number')->nullable()->unique()->index();

            $table->string('bol')->nullable();
            $table->string('po_number')->nullable();

            $table->string('status')
                ->default('Pending');

            $table->foreignId('shipper_location_id')
                ->constrained('locations')
                ->onDelete('restrict');

            $table->foreignId('dc_location_id')
                ->constrained('locations')
                ->onDelete('restrict');

            $table->foreignId('carrier_id')
                ->nullable()
                ->constrained('carriers')
                ->onDelete('set null');

            $table->date('drop_date')->nullable();
            $table->dateTime('pickup_date')->nullable();
            $table->dateTime('delivery_date')->nullable();

            $table->unsignedInteger('rack_qty')->default(0);
            $table->unsignedInteger('load_bar_qty')->default(0);
            $table->unsignedInteger('strap_qty')->default(0);

            $table->string('trailer')->nullable();
            $table->string('drayage')->nullable();

            $table->dateTime('on_site')->nullable();
            $table->dateTime('shipped')->nullable();
            $table->dateTime('crossed_border')->nullable();
            $table->dateTime('recycling_sent')->nullable();
            $table->dateTime('paperwork_sent')->nullable();
            $table->dateTime('delivery_sent')->nullable();  // was delivery_alert_sent in some versions

            $table->string('consolidation_number')->nullable();

            $table->text('notes')->nullable();

            $table->json('other')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
