<?php

use App\Http\Controllers\AuditController;
use App\Http\Controllers\CarrierController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\RateController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ScheduledItemController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

// explicitly attach our validation middleware to the auth route
// (``Broadcast::routes`` lives inside the existing ``web`` group so we
// need to repeat those entries here).  Using the class reference avoids
// needing to register an alias.
Broadcast::routes([
    'middleware' => [
        'web',
        'auth',
        \App\Http\Middleware\EnsureSocketId::class,
    ],
]);

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', [DashboardController::class, 'index'])->middleware(['auth'])->name('dashboard');

Route::middleware(['auth', 'role:administrator'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('audits', [AuditController::class, 'index'])->name('audits.index');

    // Role Routes
    Route::get('roles/export', [RoleController::class, 'export'])->name('roles.export');
    Route::post('roles/import', [RoleController::class, 'import'])->name('roles.import');
    Route::resource('roles', RoleController::class);

    // User Routes
    Route::get('users/export', [UserController::class, 'export'])->name('users.export');
    Route::post('users/import', [UserController::class, 'import'])->name('users.import');
    Route::patch('users/{user}/disable', [UserController::class, 'disable'])->name('users.disable');
    Route::patch('users/{user}/enable', [UserController::class, 'enable'])->name('users.enable');
    Route::delete('users/{user}/delete', [UserController::class, 'delete'])->name('users.delete');
    Route::patch('users/{user}/restore', [UserController::class, 'restore'])->withTrashed()->name('users.restore');
    Route::resource('users', UserController::class)->only(['index', 'edit', 'update']);
});

Route::middleware(['auth', 'role:administrator|supervisor|truckload|carrier'])->prefix('admin')->name('admin.')->group(function () {
    Route::middleware('role:administrator|supervisor')->group(function () {
        Route::post('carriers/import', [CarrierController::class, 'import'])->name('carriers.import');
        Route::get('carriers/export', [CarrierController::class, 'export'])->name('carriers.export');
    });
    Route::resource('carriers', CarrierController::class);
});

Route::middleware(['auth', 'role:administrator|supervisor'])->prefix('admin')->name('admin.')->group(function () {
    // Carrier Routes

    // Templates
    Route::get('templates/export', [TemplateController::class, 'export'])->name('templates.export');
    Route::post('templates/import', [TemplateController::class, 'import'])->name('templates.import');
    Route::resource('templates', TemplateController::class);

    // Scheduled Items
    Route::resource('scheduled-items', ScheduledItemController::class);
    // Location Routes
    Route::get('locations/recycling-distances', [LocationController::class, 'recyclingDistances'])->name('locations.recycling-distances');
    Route::get('locations/distances/{dc_id}/{rec_id}/map', [LocationController::class, 'recyclingDistanceMap'])->name('locations.recycling-distance-map');
    Route::get('locations/multi-route', [LocationController::class, 'multiRoute'])->name('locations.multi-route');
    Route::post('locations/multi-route', [LocationController::class, 'calculateMultiRoute'])->name('locations.multi-route-calculate');
    Route::post('locations/import', [LocationController::class, 'import'])->name('locations.import');
    Route::get('locations/export', [LocationController::class, 'export'])->name('locations.export');
    Route::resource('locations', LocationController::class)->names([
        'index' => 'locations.index',
        'create' => 'locations.create',
        'store' => 'locations.store',
        'show' => 'locations.show',
        'edit' => 'locations.edit',
        'update' => 'locations.update',
        'destroy' => 'locations.destroy',
    ]);
    // Rate Routes
    Route::resource('rates', RateController::class);
});

Route::middleware(['auth', 'role:administrator|supervisor|truckload|carrier'])->prefix('admin')->name('admin.')->group(function () {
    // Shipments - main index route handles both GET (initial load, pagination) and POST (filter/search)
    Route::match(['get', 'post'], 'shipments', [ShipmentController::class, 'index'])
        ->name('shipments.index');

    // Create / Store
    Route::get('shipments/create', [ShipmentController::class, 'create'])
        ->name('shipments.create');

    Route::post('shipments/create', [ShipmentController::class, 'store'])
        ->name('shipments.store');

    // Show, Edit, Update, Delete
    Route::get('shipments/{shipment}', [ShipmentController::class, 'show'])
        ->name('shipments.show');

    Route::get('shipments/{shipment}/edit', [ShipmentController::class, 'edit'])
        ->name('shipments.edit');

    Route::put('shipments/{shipment}', [ShipmentController::class, 'update'])
        ->name('shipments.update');

    Route::patch('shipments/{shipment}/offers', [ShipmentController::class, 'updateOffers'])
        ->name('shipments.update-offers');

    Route::patch('shipments/{shipment}/quick-update', [ShipmentController::class, 'quickUpdate'])
        ->name('shipments.quick-update');

    Route::delete('shipments/{shipment}', [ShipmentController::class, 'destroy'])
        ->name('shipments.destroy');

    // Custom actions (from your earlier code)
    Route::post('shipments/google-sheets-import', [ShipmentController::class, 'googleSheetsImport'])
        ->name('shipments.google-sheets-import');

    Route::post('shipments/pbi-import', [ShipmentController::class, 'pbiImport'])
        ->name('shipments.pbi-import');

    Route::get('shipments/{shipment}/calculate-bol', [ShipmentController::class, 'calculateBol'])
        ->name('shipments.calculate-bol');

    Route::post('shipments/{shipment}/send-paperwork', [ShipmentController::class, 'sendPaperwork'])
        ->name('shipments.send-paperwork');
});

Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    // Note Routes
    Route::post('notes', [NoteController::class, 'store'])->name('notes.store');
    Route::delete('notes/{note}', [NoteController::class, 'destroy'])->name('notes.destroy');
});

require __DIR__.'/settings.php';
