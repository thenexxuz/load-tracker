<?php

it('keeps carrier show loading active trailer assignments for undelivered shipments', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $controller = file_get_contents($projectRoot.'/app/Http/Controllers/CarrierController.php');

    expect($controller)
        ->toContain('use App\\Models\\Shipment;')
        ->toContain("$carrier->load(['notes.user', 'trailers.currentLocation']);")
        ->toContain('Shipment::query()')
        ->toContain("->whereHas('trailer', function (\$query) use (\$carrier) {")
        ->toContain("->whereRaw(\"LOWER(status) NOT IN ('delivered', 'cancelled')\")")
        ->toContain("'trailer:id,number',")
        ->toContain("'pickupLocation:id,name,short_code',")
        ->toContain("\$trailer = \$shipment->getRelation('trailer');")
        ->toContain("'trailer_number' => \$trailer?->number")
        ->toContain("'shipment_number' => \$shipment->shipment_number")
        ->toContain("'consolidation_number' => \$shipment->consolidation_number")
        ->toContain("'bol' => \$shipment->bol")
        ->toContain("'pickup_location_name' => \$shipment->pickupLocation?->name")
        ->toContain("'is_assigned_to_shipment' => true")
        ->toContain("->whereNotIn('id', \$assignedTrailerIds)")
        ->toContain("'pickup_location_name' => \$trailer->currentLocation?->name")
        ->toContain("'is_assigned_to_shipment' => false")
        ->toContain("'activeTrailerAssignments' => \$activeTrailerAssignments");
});

it('keeps carrier show rendering the trailer assignment table', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $page = file_get_contents($projectRoot.'/resources/js/pages/Admin/Carriers/Show.vue');

    expect($page)
        ->toContain('activeTrailerAssignments: Array<{')
        ->toContain('consolidation_number: string | null')
        ->toContain('Trailers Assigned Or Parked At Pickup Locations')
        ->toContain('Trailer Number')
        ->toContain('Shipment Number')
        ->toContain('Assignment')
        ->toContain('Pickup Location')
        ->toContain('v-for="assignment in activeTrailerAssignments"')
        ->toContain("{{ assignment.trailer_number ?? '—' }}")
        ->toContain("{{ assignment.shipment_number ?? '—' }}")
        ->toContain("{{ assignment.is_assigned_to_shipment ? 'Assigned' : 'Unassigned' }}")
        ->toContain("{{ assignment.pickup_location_name ?? '—' }}")
        ->toContain('No trailers are currently assigned to undelivered shipments or parked at pickup locations.');
});
