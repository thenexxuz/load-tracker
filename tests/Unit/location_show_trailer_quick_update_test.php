<?php

it('keeps shipment quick update wired for typed alphanumeric trailer numbers', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $controller = file_get_contents($projectRoot.'/app/Http/Controllers/ShipmentController.php');

    expect($controller)
        ->toContain("'trailer_number' => 'nullable|string|max:100'")
        ->toContain('$trailerNumber = trim((string) ($validated[\'trailer_number\'] ?? \'\'));')
        ->toContain('throw ValidationException::withMessages([')
        ->toContain("'carrier_id' => 'Select a carrier before assigning a trailer number.'")
        ->toContain('->whereRaw(\'LOWER(number) = ?\', [Str::lower($trailerNumber)])')
        ->toContain('$trailer = Trailer::create([')
        ->toContain("'number' => \$trailerNumber")
        ->toContain("'status' => 'available'")
        ->toContain("'trailer_number' => \$shipment->trailer?->number");
});

it('keeps the location show trailer editor submitting typed trailer numbers', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $page = file_get_contents($projectRoot.'/resources/js/pages/Admin/Locations/Show.vue');

    expect($page)
        ->toContain("trailerSearchInput.value = shipment?.trailer_number || ''")
        ->toContain('trailer_number: trailerSearchInput.value.trim() || null')
        ->toContain('watch(')
        ->toContain('const normalizedTrailerNumber = nextTrailerNumber.trim().toLowerCase()')
        ->toContain('editForm.value.trailer_id = matchingTrailer?.id ?? null')
        ->toContain('This trailer will be created when you save.');
});
