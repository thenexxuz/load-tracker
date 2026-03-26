<?php

it('adds a google sheets import action to the shipments index', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $page = file_get_contents($projectRoot.'/resources/js/pages/Admin/Shipments/Index.vue');
    $routes = file_get_contents($projectRoot.'/routes/web.php');

    expect($page)
        ->toContain('const showGoogleSheetsImportModal = ref(false)')
        ->toContain('const googleSheetsImportForm = useForm({')
        ->toContain("googleSheetsImportForm.post(route('admin.shipments.google-sheets-import')")
        ->toContain('Import from Google Sheets')
        ->toContain('Import Shipment Changes from Google Sheets')
        ->toContain('The sheet must be shared or published so the server can access it.')
        ->toContain('trailer number');

    expect($routes)
        ->toContain("Route::post('shipments/google-sheets-import', [ShipmentController::class, 'googleSheetsImport'])")
        ->toContain("->name('shipments.google-sheets-import');");
});
