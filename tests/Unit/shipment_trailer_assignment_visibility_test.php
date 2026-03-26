<?php

it('keeps shipment offer sections hidden when a carrier is assigned', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $showPage = file_get_contents($projectRoot.'/resources/js/pages/Admin/Shipments/Show.vue');
    $editPage = file_get_contents($projectRoot.'/resources/js/pages/Admin/Shipments/Edit.vue');

    expect($showPage)
        ->toContain('v-if="hasAdminAccess && !hasAssignedCarrier"')
        ->toContain('Offer Shipment To Carriers');

    expect($editPage)
        ->toContain('<div v-if="form.carrier_id === null">')
        ->toContain('Carrier users assigned to the selected carriers will see this unassigned shipment on their Shipment Index.');
});

it('keeps shipment updates clearing trailer assignments when the carrier is removed', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $controller = file_get_contents($projectRoot.'/app/Http/Controllers/ShipmentController.php');

    expect($controller)
        ->toContain('private function syncTrailerAssignments(Shipment $shipment, array &$validated): void')
        ->toContain('if (blank($nextCarrierId)) {')
        ->toContain("\$validated['trailer_id'] = null;")
        ->toContain("\$validated['loaned_from_trailer_id'] = null;")
        ->toContain("'current_location_id' => \$pickupLocationId")
        ->toContain("'status' => 'available'")
        ->toContain("'status' => 'in_use'")
        ->toContain('$this->syncTrailerAssignments($shipment, $validated);');
});
