<?php

use App\Http\Controllers\AppsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConnectionsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\SyncAllController;
use Illuminate\Support\Facades\Route;

// ── Public ──
Route::match(['get', 'post'], '/login', [AuthController::class, 'login'])->name('login');
Route::get('/logout', [AuthController::class, 'logout'])->name('logout');
Route::match(['get', 'post'], '/setup', [SetupController::class, 'run'])->name('setup');

// ── Authenticated ──
Route::middleware('auth.pin')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('home');

    // Report (Excel-style daily report, filters, country report, history, export)
    Route::get('/report', [ReportController::class, 'index'])->name('report');
    Route::get('/report/country', [ReportController::class, 'country'])->name('report.country');
    Route::get('/report/history', [ReportController::class, 'history'])->name('report.history');
    Route::get('/report/export', [ReportController::class, 'export'])->name('report.export');

    // Apps management (add / edit / delete)
    Route::get('/apps', [AppsController::class, 'index'])->name('apps');
    Route::post('/apps', [AppsController::class, 'store'])->name('apps.store');
    Route::get('/apps/{app}/edit', [AppsController::class, 'edit'])->name('apps.edit');
    Route::put('/apps/{app}', [AppsController::class, 'update'])->name('apps.update');
    Route::delete('/apps/{app}', [AppsController::class, 'destroy'])->name('apps.destroy');

    // Sync All — pull every campaign from every accessible account
    Route::get('/sync-all', [SyncAllController::class, 'index'])->name('sync-all');
    Route::post('/sync-all', [SyncAllController::class, 'run'])->name('sync-all.run');
    Route::post('/sync-all/app', [SyncAllController::class, 'runApp'])->name('sync-all.app');

    // Ad Accounts — Google Ads Manager connections (per-MCC credentials)
    Route::get('/connections', [ConnectionsController::class, 'index'])->name('connections');
    Route::post('/connections', [ConnectionsController::class, 'store'])->name('connections.store');
    Route::post('/connections/{connection}/test', [ConnectionsController::class, 'test'])->name('connections.test');
    Route::get('/connections/{connection}/edit', [ConnectionsController::class, 'edit'])->name('connections.edit');
    Route::put('/connections/{connection}', [ConnectionsController::class, 'update'])->name('connections.update');
    Route::delete('/connections/{connection}', [ConnectionsController::class, 'destroy'])->name('connections.destroy');
});
