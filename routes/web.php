<?php

use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'))->name('home');

Route::middleware(['auth', 'verified', 'tenant'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::resource('companies', CompanyController::class)->only(['index', 'store']);
});

require __DIR__.'/settings.php';
