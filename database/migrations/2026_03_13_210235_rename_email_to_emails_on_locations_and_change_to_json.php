<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            // Rename old single email column
            $table->renameColumn('email', 'emails');

            // Change it from string → json (nullable)
            $table->json('emails')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            // Revert json back to string (data loss possible if multiple emails exist)
            $table->string('emails')->nullable()->change();

            // Rename back
            $table->renameColumn('emails', 'email');
        });
    }
};
