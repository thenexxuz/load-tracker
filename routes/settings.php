<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Settings\AppSettingsController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\TwoFactorAuthenticationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('user-password.edit');

    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/Appearance');
    })->name('appearance.edit');

    Route::get('settings/two-factor', [TwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');

    Route::middleware(['role:administrator|supervisor'])->group(function () {
        Route::get('settings/dashboard', [DashboardController::class, 'editPreferences'])
            ->name('dashboard-preferences.edit');
        Route::patch('settings/dashboard', [DashboardController::class, 'updatePreferences'])
            ->name('dashboard-preferences.update');
    });
});

Route::middleware(['auth', 'role:administrator'])->group(function () {
    Route::get('settings/app', [AppSettingsController::class, 'edit'])->name('app-settings.edit');
    Route::patch('settings/app', [AppSettingsController::class, 'update'])->name('app-settings.update');
});
