<?php

use App\Http\Controllers\AuditController;
use App\Http\Controllers\CarrierController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'role:administrator'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('audits', [AuditController::class, 'index'])->name('audits.index');
    Route::resource('roles', RoleController::class);
    Route::resource('users', UserController::class)->only(['index', 'edit', 'update']);
});

Route::middleware(['auth', 'role:administrator|supervisor'])->prefix('admin')->name('admin.')->group(function () {
    // Carrier Routes
    Route::post('carriers/import', [CarrierController::class, 'import'])->name('carriers.import');
    Route::get('carriers/export', [CarrierController::class, 'export'])->name('carriers.export');
    Route::resource('carriers', CarrierController::class);
    // Location Routes
    Route::post('locations/import', [LocationController::class, 'import'])->name('locations.import');
    Route::get('locations/export', [LocationController::class, 'export'])->name('locations.export');
    Route::resource('locations', LocationController::class);
    // Shipment Routes
    Route::post('shipments/pbi-import', [ShipmentController::class, 'pbiImport'])->name('shipments.pbi-import');
    Route::get('shipments/failed-tsv', [ShipmentController::class, 'downloadFailedTsv'])->name('shipments.download-failed-tsv');

    Route::resource('shipments', ShipmentController::class);
});

require __DIR__.'/settings.php';
