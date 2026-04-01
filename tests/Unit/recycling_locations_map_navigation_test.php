<?php

it('keeps recycling locations map navigation scoped to locations context', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $sidebar = file_get_contents($projectRoot.'/resources/js/components/AppSidebar.vue');
    $routes = file_get_contents($projectRoot.'/routes/web.php');
    $controller = file_get_contents($projectRoot.'/app/Http/Controllers/LocationController.php');
    $page = file_get_contents($projectRoot.'/resources/js/pages/Admin/Locations/RecyclingLocationsMap.vue');

    expect($sidebar)
        ->toContain("title: 'Recycling Map'")
        ->toContain("href: route('admin.locations.recycling-map')")
        ->toContain('...(hasLocationsAccess && isOnLocations.value');

    expect($routes)
        ->toContain("Route::get('locations/recycling-map', [LocationController::class, 'recyclingMap'])->name('locations.recycling-map');");

    expect($controller)
        ->toContain('public function recyclingMap()')
        ->toContain("return Inertia::render('Admin/Locations/RecyclingLocationsMap'")
        ->toContain("->where('type', 'recycling')");

    expect($page)
        ->toContain('<Head title="Recycling Locations Map" />')
        ->toContain("route('admin.locations.show', location.id)")
        ->toContain('new mapboxgl.Marker({ color: \'#f97316\' })');
});
