<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('app_settings')) {
            return;
        }

        $hasKey = Schema::hasColumn('app_settings', 'key');
        $hasValue = Schema::hasColumn('app_settings', 'value');

        if ($hasKey && $hasValue) {
            return;
        }

        $googleSheetUrl = Schema::hasColumn('app_settings', 'google_sheet_url')
            ? DB::table('app_settings')->value('google_sheet_url')
            : null;

        Schema::drop('app_settings');

        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        if ($googleSheetUrl !== null && $googleSheetUrl !== '') {
            DB::table('app_settings')->insert([
                'key' => 'google_sheet_url',
                'value' => $googleSheetUrl,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('app_settings')) {
            return;
        }

        $hasLegacyColumn = Schema::hasColumn('app_settings', 'google_sheet_url');

        if ($hasLegacyColumn) {
            return;
        }

        $googleSheetUrl = DB::table('app_settings')
            ->where('key', 'google_sheet_url')
            ->value('value');

        Schema::drop('app_settings');

        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('google_sheet_url', 2048)->nullable();
            $table->timestamps();
        });

        if ($googleSheetUrl !== null && $googleSheetUrl !== '') {
            DB::table('app_settings')->insert([
                'google_sheet_url' => $googleSheetUrl,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
