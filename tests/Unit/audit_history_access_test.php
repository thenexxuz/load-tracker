<?php

it('keeps audit history restricted to administrators only', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $sidebar = file_get_contents($projectRoot.'/resources/js/components/AppSidebar.vue');
    $controller = file_get_contents($projectRoot.'/app/Http/Controllers/AuditController.php');
    $routes = file_get_contents($projectRoot.'/routes/web.php');

    expect($sidebar)
        ->toContain("const hasAuditAccess = userRoles.includes('administrator')")
        ->not->toContain("const hasAuditAccess = userRoles.includes('administrator') || userRoles.includes('supervisor')")
        ->toContain("title: 'Audit History'");

    expect($controller)
        ->toContain('public function index(Request $request): Response')
        ->toContain("abort_unless(\$request->user()?->hasRole('administrator'), 403);");

    expect($routes)
        ->toContain("Route::middleware(['auth', 'role:administrator'])->prefix('admin')->name('admin.')->group(function () {")
        ->toContain("Route::get('audits', [AuditController::class, 'index'])->name('audits.index');");
});
