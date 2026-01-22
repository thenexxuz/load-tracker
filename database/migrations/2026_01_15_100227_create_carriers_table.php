<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carriers', function (Blueprint $table) {
            $table->id();
            $table->uuid('guid')->unique()->index();
            $table->string('short_code', 50)->unique()->index();
            $table->string('wt_code', 50)->nullable()->index();
            $table->string('name', 255);
            $table->text('emails')->nullable()->comment('Semicolon-separated list of email addresses');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carriers');
    }
};
