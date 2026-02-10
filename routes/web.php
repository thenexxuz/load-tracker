<?php

use App\Http\Controllers\AuditController;
use App\Http\Controllers\CarrierController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\RateController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\TemplateController;
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

Route::middleware(['auth', 'role:carrier'])->prefix('admin')->name('admin.')->group(function () {
    Route::middleware('role:administrator|supervisor')->group(function () {
        Route::post('carriers/import', [CarrierController::class, 'import'])->name('carriers.import');
        Route::get('carriers/export', [CarrierController::class, 'export'])->name('carriers.export');
    });
    Route::resource('carriers', CarrierController::class);
});

Route::middleware(['auth', 'role:administrator|supervisor'])->prefix('admin')->name('admin.')->group(function () {
    // Carrier Routes
    
    // Template Routes
    Route::resource('templates', TemplateController::class);
    // Location Routes
    Route::get('locations/recycling-distances', [LocationController::class, 'recyclingDistances'])->name('locations.recycling-distances');
    Route::get('locations/recycling-distances/{dc_id}/{rec_id}/map', [LocationController::class, 'recyclingDistanceMap'])->name('locations.recycling-distance-map');
    Route::get('locations/multi-route', [LocationController::class, 'multiRoute'])->name('locations.multi-route');
    Route::post('locations/multi-route', [LocationController::class, 'calculateMultiRoute'])->name('locations.multi-route-calculate');
    Route::post('locations/import', [LocationController::class, 'import'])->name('locations.import');
    Route::get('locations/export', [LocationController::class, 'export'])->name('locations.export');
    Route::resource('locations', LocationController::class);
    // Rate Routes
    Route::resource('rates', RateController::class);
});

Route::middleware(['auth', 'role:administrator|supervisor|truckload'])->prefix('admin')->name('admin.')->group(function () {
    // Shipment Routes
    Route::post('shipments/pbi-import', [ShipmentController::class, 'pbiImport'])->name('shipments.pbi-import');
    Route::get('shipments/failed-tsv', [ShipmentController::class, 'downloadFailedTsv'])->name('shipments.download-failed-tsv');
    Route::post('shipments/{shipment}/send-paperwork', [ShipmentController::class, 'processSendPaperwork'])->name('shipments.process-send-paperwork');
    Route::get('shipments/{shipment}/send-paperwork', [ShipmentController::class, 'sendPaperwork'])->name('shipments.send-paperwork');
    Route::post('shipments/filter', [ShipmentController::class, 'index'])->name('shipments.filter');
    Route::get('shipments/filter', function () {
        return redirect()->route('admin.shipments.index');
    });
    Route::resource('shipments', ShipmentController::class);
});

require __DIR__.'/settings.php';
