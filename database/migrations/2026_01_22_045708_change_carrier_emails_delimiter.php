<?php

use App\Models\Carrier;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Carrier::query()->where('emails', 'like', '%,%')->update([
            'emails' => DB::raw("REPLACE(emails, ',', ';')")
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Carrier::query()->where('emails', 'like', '%,%')->update([
            'emails' => DB::raw("REPLACE(emails, ';', ',')")
        ]);
    }
};
