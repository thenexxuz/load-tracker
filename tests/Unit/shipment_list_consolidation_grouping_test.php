<?php

it('keeps shipment and location list pages wired for linked consolidation hover states', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $shipmentsPage = file_get_contents($projectRoot.'/resources/js/pages/Admin/Shipments/Index.vue');
    $locationPage = file_get_contents($projectRoot.'/resources/js/pages/Admin/Locations/Show.vue');

    expect($shipmentsPage)
        ->toContain('const hoveredConsolidationNumber = ref<string | null>(null)')
        ->toContain('const setHoveredConsolidationNumber = (consolidationNumber: string | null): void => {')
        ->toContain('const clearHoveredConsolidationNumber = (): void => {')
        ->toContain('const isHoveredConsolidation = (consolidationNumber: string | null): boolean => {')
        ->toContain('@mouseenter="setHoveredConsolidationNumber(shipment.consolidation_number)"')
        ->toContain('@mouseleave="clearHoveredConsolidationNumber"');

    expect($locationPage)
        ->toContain('const hoveredConsolidationNumber = ref<string | null>(null)')
        ->toContain('const setHoveredConsolidationNumber = (consolidationNumber: string | null): void => {')
        ->toContain('const clearHoveredConsolidationNumber = (): void => {')
        ->toContain('const isHoveredConsolidation = (consolidationNumber: string | null): boolean => {')
        ->toContain('@mouseenter="setHoveredConsolidationNumber(shipment.consolidation_number)"')
        ->toContain('@mouseleave="clearHoveredConsolidationNumber"');
});

it('keeps shipment list controllers grouping consolidated rows together', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $shipmentController = file_get_contents($projectRoot.'/app/Http/Controllers/ShipmentController.php');
    $locationController = file_get_contents($projectRoot.'/app/Http/Controllers/LocationController.php');
    $carrierController = file_get_contents($projectRoot.'/app/Http/Controllers/CarrierController.php');
    $carrierPage = file_get_contents($projectRoot.'/resources/js/pages/Admin/Carriers/Show.vue');

    expect($shipmentController)
        ->toContain('->groupBy(fn (Shipment $shipment): string => filled($shipment->consolidation_number)')
        ->toContain("? 'consolidation:'.\$shipment->consolidation_number")
        ->toContain("? \$shipmentGroup->sortBy('shipment_number', SORT_NATURAL)->values()");

    expect($locationController)
        ->toContain('->groupBy(fn ($shipment): string => filled($shipment->consolidation_number)')
        ->toContain("? 'consolidation:'.\$shipment->consolidation_number")
        ->toContain("? \$shipmentGroup->sortBy('shipment_number', SORT_NATURAL)->values()");

    expect($carrierController)
        ->toContain('->groupBy(fn (Shipment $shipment): string => filled($shipment->consolidation_number)')
        ->toContain("? 'consolidation:'.\$shipment->consolidation_number")
        ->toContain("? \$shipmentGroup->sortBy('shipment_number', SORT_NATURAL)->values()");

    expect($carrierPage)
        ->toContain('const hoveredConsolidationNumber = ref<string | null>(null)')
        ->toContain('@mouseenter="setHoveredConsolidationNumber(assignment.consolidation_number)"')
        ->toContain('@mouseleave="clearHoveredConsolidationNumber"');
});
