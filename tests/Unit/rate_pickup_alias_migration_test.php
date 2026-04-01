<?php

it('keeps the rates pickup alias migration remapping elp rjs to wiwynn rjs', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $migration = file_get_contents($projectRoot.'/database/migrations/2026_04_01_183525_remap_elp_rjs_rates_to_wiwynn_rjs.php');

    expect($migration)
        ->toContain("whereRaw('LOWER(short_code) = ?', ['elp-rjs'])")
        ->toContain("whereRaw('LOWER(short_code) = ?', ['wiwynn - rjs'])")
        ->toContain("DB::table('rates')")
        ->toContain("->where('pickup_location_id', $oldPickupLocationId)")
        ->toContain("->update(['pickup_location_id' => $newPickupLocationId]);");
});
