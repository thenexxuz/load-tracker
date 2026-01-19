<?php

use App\Http\Controllers\CarrierController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\RoleController;
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
    Route::resource('roles', RoleController::class);
    Route::resource('users', UserController::class)->only(['index', 'edit', 'update']);
});

Route::middleware(['auth', 'role:administrator|supervisor'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('carriers', CarrierController::class);
    Route::resource('locations', LocationController::class);
});

require __DIR__.'/settings.php';
