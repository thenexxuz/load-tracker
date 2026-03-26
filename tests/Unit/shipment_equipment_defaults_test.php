<?php

use App\Models\Shipment;

it('returns the configured default equipment counts for rack quantities', function (): void {
    expect(Shipment::defaultEquipmentCountsForRackQty(0))->toBe([
        'load_bar_qty' => 0,
        'strap_qty' => 0,
    ]);

    expect(Shipment::defaultEquipmentCountsForRackQty(1))->toBe([
        'load_bar_qty' => 2,
        'strap_qty' => 3,
    ]);

    expect(Shipment::defaultEquipmentCountsForRackQty(5))->toBe([
        'load_bar_qty' => 2,
        'strap_qty' => 13,
    ]);

    expect(Shipment::defaultEquipmentCountsForRackQty(17))->toBe([
        'load_bar_qty' => 2,
        'strap_qty' => 43,
    ]);

    expect(Shipment::defaultEquipmentCountsForRackQty(20))->toBe([
        'load_bar_qty' => 2,
        'strap_qty' => 50,
    ]);

    expect(Shipment::defaultEquipmentCountsForRackQty(21))->toBe([
        'load_bar_qty' => 2,
        'strap_qty' => 50,
    ]);
});

it('keeps shipment equipment defaults wired into shipment forms and imports', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $controller = file_get_contents($projectRoot.'/app/Http/Controllers/ShipmentController.php');
    $createPage = file_get_contents($projectRoot.'/resources/js/pages/Admin/Shipments/Create.vue');
    $editPage = file_get_contents($projectRoot.'/resources/js/pages/Admin/Shipments/Edit.vue');
    $helper = file_get_contents($projectRoot.'/resources/js/lib/shipmentEquipmentDefaults.ts');
    $composable = file_get_contents($projectRoot.'/resources/js/composables/useShipmentEquipmentDefaults.ts');
    $migration = file_get_contents($projectRoot.'/database/migrations/2026_03_26_165605_backfill_shipment_equipment_defaults.php');

    expect($helper)
        ->toContain('const strapQtyByRackQty: Record<number, number> = {')
        ->toContain('17: 43,')
        ->toContain('20: 50,')
        ->toContain('loadBarQty: 2,');

    expect($composable)
        ->toContain('watch(() => form.rack_qty, (rackQty) => {')
        ->toContain('form.load_bar_qty === lastSuggestedEquipment.value.loadBarQty || form.load_bar_qty === 0')
        ->toContain('form.strap_qty === lastSuggestedEquipment.value.strapQty || form.strap_qty === 0');

    expect($createPage)
        ->toContain("import { useShipmentEquipmentDefaults } from '@/composables/useShipmentEquipmentDefaults'")
        ->toContain('useShipmentEquipmentDefaults(form)');

    expect($editPage)
        ->toContain("import { useShipmentEquipmentDefaults } from '@/composables/useShipmentEquipmentDefaults'")
        ->toContain('useShipmentEquipmentDefaults(form)');

    expect($controller)
        ->toContain('Shipment::defaultEquipmentCountsForRackQty((int) $validated[\'sum of pallets\'])')
        ->toContain('\'load_bar_qty\' => $equipmentDefaults[\'load_bar_qty\'],')
        ->toContain('\'strap_qty\' => $equipmentDefaults[\'strap_qty\'],');

    expect($migration)
        ->toContain('DB::table(\'shipments\')')
        ->toContain('->chunkById(200, function ($shipments): void {')
        ->toContain('$equipmentDefaults = $this->defaultEquipmentCountsForRackQty((int) $shipment->rack_qty);')
        ->toContain('\'load_bar_qty\' => $equipmentDefaults[\'load_bar_qty\'],')
        ->toContain('\'strap_qty\' => $equipmentDefaults[\'strap_qty\'],')
        ->toContain('private function defaultEquipmentCountsForRackQty(int $rackQty): array');
});
