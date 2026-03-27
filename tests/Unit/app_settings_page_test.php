<?php

it('keeps app settings routing and persistence wired for administrator-only google sheets configuration', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $routes = file_get_contents($projectRoot.'/routes/settings.php');
    $controller = file_get_contents($projectRoot.'/app/Http/Controllers/Settings/AppSettingsController.php');
    $request = file_get_contents($projectRoot.'/app/Http/Requests/Settings/AppSettingsUpdateRequest.php');
    $model = file_get_contents($projectRoot.'/app/Models/AppSetting.php');
    $migration = file_get_contents($projectRoot.'/database/migrations/2026_03_27_000000_create_app_settings_table.php');

    expect($routes)
        ->toContain("Route::middleware(['auth', 'role:administrator'])->group(function () {")
        ->toContain("Route::get('settings/app', [AppSettingsController::class, 'edit'])->name('app-settings.edit');")
        ->toContain("Route::patch('settings/app', [AppSettingsController::class, 'update'])->name('app-settings.update');");

    expect($controller)
        ->toContain("return Inertia::render('settings/App', [")
        ->toContain("'google_sheet_url' => AppSetting::getValue(AppSetting::GOOGLE_SHEET_URL_KEY),")
        ->toContain('AppSetting::setValue(')
        ->toContain('$request->validated(\'google_sheet_url\')');

    expect($request)
        ->toContain("'google_sheet_url' => ['nullable', 'url', 'max:2048']")
        ->toContain("'google_sheet_url.url' => 'Enter a valid Google Sheets URL.'");

    expect($model)
        ->toContain("public const GOOGLE_SHEET_URL_KEY = 'google_sheet_url';")
        ->toContain("'key',")
        ->toContain("'value',")
        ->toContain('public static function getValue(string $key, ?string $default = null): ?string')
        ->toContain('public static function setValue(string $key, ?string $value): self');

    expect($migration)
        ->toContain("Schema::create('app_settings'")
        ->toContain('$table->string(\'key\')->unique();')
        ->toContain('$table->text(\'value\')->nullable();');
});

it('keeps the app settings page and shipment import ui aligned with the stored google sheets url', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $layout = file_get_contents($projectRoot.'/resources/js/layouts/settings/Layout.vue');
    $page = file_get_contents($projectRoot.'/resources/js/pages/settings/App.vue');
    $action = file_get_contents($projectRoot.'/resources/js/actions/App/Http/Controllers/Settings/AppSettingsController.ts');
    $shipmentsController = file_get_contents($projectRoot.'/app/Http/Controllers/ShipmentController.php');
    $shipmentsPage = file_get_contents($projectRoot.'/resources/js/pages/Admin/Shipments/Index.vue');

    expect($layout)
        ->toContain("const hasAppSettingsAccess = userRoles.includes('administrator');")
        ->toContain("title: 'App Settings',")
        ->toContain('href: editAppSettings(),');

    expect($page)
        ->toContain('AppSettingsController.update.form()')
        ->toContain('Google Sheets URL')
        ->toContain('This URL is used as the default source for shipment imports from Google Sheets.');

    expect($action)
        ->toContain("url: '/settings/app',")
        ->toContain("method: 'patch',");

    expect($shipmentsController)
        ->toContain("'googleSheetsUrl' => AppSetting::getValue(AppSetting::GOOGLE_SHEET_URL_KEY),");

    expect($shipmentsPage)
        ->toContain('googleSheetsUrl: string | null')
        ->toContain("google_sheet_url: props.googleSheetsUrl ?? '',")
        ->toContain("googleSheetsImportForm.defaults('google_sheet_url', props.googleSheetsUrl ?? '')");
});
