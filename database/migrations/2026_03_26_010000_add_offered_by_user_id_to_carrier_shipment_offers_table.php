<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carrier_shipment_offers', function (Blueprint $table) {
            $table->foreignId('offered_by_user_id')
                ->nullable()
                ->after('carrier_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('carrier_shipment_offers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('offered_by_user_id');
        });
    }
};
