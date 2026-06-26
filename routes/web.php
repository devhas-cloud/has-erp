<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConfigurationController;
use App\Http\Controllers\LeadsManagementController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware(['auth', 'access.control'])->group(function () {
    Route::get('/', function () {
        return redirect()->route('user-management.index');
    });

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::resource('user-management', UserManagementController::class);

    Route::get('/configuration', [ConfigurationController::class, 'index'])->name('configuration.index');
    Route::get('/configuration/{table}', [ConfigurationController::class, 'list'])->name('configuration.list');
    Route::post('/configuration/{table}', [ConfigurationController::class, 'store'])->name('configuration.store');
    Route::put('/configuration/{table}/{id}', [ConfigurationController::class, 'update'])->name('configuration.update');
    Route::delete('/configuration/{table}/{id}', [ConfigurationController::class, 'destroy'])->name('configuration.destroy');

    Route::get('leads-management/data', [LeadsManagementController::class, 'data'])->name('leads-management.data');
    Route::resource('leads-management', LeadsManagementController::class);
});
