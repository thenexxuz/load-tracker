<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carrier_shipment_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('carrier_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['shipment_id', 'carrier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carrier_shipment_offers');
    }
};
