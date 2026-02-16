<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->id();

            // Polymorphic relation
            $table->morphs('notable');  // creates notable_id + notable_type

            $table->text('content');
            $table->boolean('is_admin')->default(false); // whether this note is only visible to admins
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // who wrote it

            $table->timestamps();

            $table->index(['notable_id', 'notable_type']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};