<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('locations', function (Blueprint $table) {
            // Drop the existing unique index if it exists
            $table->dropUnique(['short_code']);

            $table->unique(['short_code', 'type'], 'short_code_type_unique')
                ->where('type', '!=', 'recycling');
        });
    }

    public function down()
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropUnique('short_code_type_unique');
            $table->unique('short_code');
        });
    }
};
