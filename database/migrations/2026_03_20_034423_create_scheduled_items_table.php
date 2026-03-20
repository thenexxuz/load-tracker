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
        Schema::create('scheduled_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('schedule_type', ['daily', 'weekly', 'monthly']);
            $table->time('schedule_time');
            $table->unsignedTinyInteger('schedule_day_of_week')->nullable(); // 0-6 (Sunday-Saturday)
            $table->unsignedTinyInteger('schedule_day_of_month')->nullable(); // 1-31
            $table->foreignId('template_id')->nullable()->constrained('templates')->nullOnDelete();
            $table->morphs('schedulable');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheduled_items');
    }
};
