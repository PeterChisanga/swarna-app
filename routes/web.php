<?php

use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CrushingController;
use App\Http\Controllers\CrushingMoistureController;
use App\Http\Controllers\CrushingShiftReportController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'authenticate'])->name('login.attempt');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Crushing (PRD 8.1)
    Route::get('/crushing', [CrushingController::class, 'index'])->name('crushing.index');
    Route::post('/crushing/shift-reports', [CrushingShiftReportController::class, 'open'])->name('crushing.shift.open');
    Route::put('/crushing/shift-reports/{id}/close', [CrushingShiftReportController::class, 'close'])
        ->whereNumber('id')->name('crushing.shift.close');
    Route::post('/crushing/moisture-samples', [CrushingMoistureController::class, 'store'])->name('crushing.moisture.store');
    Route::get('/crushing/status', [CrushingController::class, 'status'])->name('crushing.status');

    // Admin review (role is checked inside ApprovalController)
    Route::prefix('approvals')->name('approvals.')->group(function () {
        Route::get('/', [ApprovalController::class, 'index'])->name('index');

        Route::post('masters/{type}', [ApprovalController::class, 'storeMaster'])
            ->whereIn('type', ['ore-types', 'streams'])->name('masters.store');
        Route::post('masters/{type}/{id}/toggle', [ApprovalController::class, 'toggleMaster'])
            ->whereIn('type', ['ore-types', 'streams'])->whereNumber('id')->name('masters.toggle');

        Route::post('{kind}/{id}/approve', [ApprovalController::class, 'approve'])
            ->whereIn('kind', ['shift', 'moisture'])->whereNumber('id')->name('approve');
        Route::post('{kind}/{id}/reject', [ApprovalController::class, 'reject'])
            ->whereIn('kind', ['shift', 'moisture'])->whereNumber('id')->name('reject');
        Route::put('{kind}/{id}', [ApprovalController::class, 'update'])
            ->whereIn('kind', ['shift', 'moisture'])->whereNumber('id')->name('update');
    });
});
