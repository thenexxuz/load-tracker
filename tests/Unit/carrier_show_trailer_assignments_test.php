<?php

it('keeps carrier show loading active trailer assignments for undelivered shipments', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $controller = file_get_contents($projectRoot.'/app/Http/Controllers/CarrierController.php');

    expect($controller)
        ->toContain('use App\\Models\\Shipment;')
        ->toContain('Shipment::query()')
        ->toContain("->whereHas('trailer', function (\$query) use (\$carrier) {")
        ->toContain("->whereRaw(\"LOWER(status) != 'delivered'\")")
        ->toContain("'trailer:id,number',")
        ->toContain("'pickupLocation:id,name,short_code',")
        ->toContain("\$trailer = \$shipment->getRelation('trailer');")
        ->toContain("'trailer_number' => \$trailer?->number")
        ->toContain("'shipment_number' => \$shipment->shipment_number")
        ->toContain("'bol' => \$shipment->bol")
        ->toContain("'pickup_location_name' => \$shipment->pickupLocation?->name")
        ->toContain("'activeTrailerAssignments' => \$activeTrailerAssignments");
});

it('keeps carrier show rendering the trailer assignment table', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $page = file_get_contents($projectRoot.'/resources/js/pages/Admin/Carriers/Show.vue');

    expect($page)
        ->toContain('activeTrailerAssignments: Array<{')
        ->toContain('Trailers Assigned To Undelivered Shipments')
        ->toContain('Trailer Number')
        ->toContain('Shipment Number')
        ->toContain('Pickup Location')
        ->toContain('v-for="assignment in activeTrailerAssignments"')
        ->toContain("{{ assignment.trailer_number ?? '—' }}")
        ->toContain("{{ assignment.shipment_number ?? '—' }}")
        ->toContain("{{ assignment.pickup_location_name ?? '—' }}")
        ->toContain('No trailers are currently assigned to undelivered shipments.');
});
