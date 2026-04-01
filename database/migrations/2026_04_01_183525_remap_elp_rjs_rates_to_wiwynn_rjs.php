<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $oldPickupLocationId = DB::table('locations')
            ->whereRaw('LOWER(short_code) = ?', ['elp-rjs'])
            ->value('id');

        $newPickupLocationId = DB::table('locations')
            ->whereRaw('LOWER(short_code) = ?', ['wiwynn - rjs'])
            ->value('id');

        if (! $oldPickupLocationId || ! $newPickupLocationId || $oldPickupLocationId === $newPickupLocationId) {
            return;
        }

        DB::table('rates')
            ->where('pickup_location_id', $oldPickupLocationId)
            ->update(['pickup_location_id' => $newPickupLocationId]);
    }

    public function down(): void
    {
        $oldPickupLocationId = DB::table('locations')
            ->whereRaw('LOWER(short_code) = ?', ['elp-rjs'])
            ->value('id');

        $newPickupLocationId = DB::table('locations')
            ->whereRaw('LOWER(short_code) = ?', ['wiwynn - rjs'])
            ->value('id');

        if (! $oldPickupLocationId || ! $newPickupLocationId || $oldPickupLocationId === $newPickupLocationId) {
            return;
        }

        DB::table('rates')
            ->where('pickup_location_id', $newPickupLocationId)
            ->update(['pickup_location_id' => $oldPickupLocationId]);
    }
};
