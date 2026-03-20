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
        Schema::table('scheduled_items', function (Blueprint $table) {
            $table->boolean('apply_to_all')->default(false)->after('template_id');
            $table->unsignedBigInteger('schedulable_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scheduled_items', function (Blueprint $table) {
            $table->dropColumn('apply_to_all');
            $table->unsignedBigInteger('schedulable_id')->nullable(false)->change();
        });
    }
};
