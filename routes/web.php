<?php

use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ContactImportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvitationAcceptanceController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LeadStageController;
use App\Http\Controllers\PipelineController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TeamMemberController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'))->name('home');

Route::middleware('guest')->group(function () {
    Route::get('invitations/{token}', [InvitationAcceptanceController::class, 'show'])->name('invitations.accept.show');
    Route::post('invitations/{token}', [InvitationAcceptanceController::class, 'store'])->name('invitations.accept.store');
});

Route::middleware(['auth', 'verified', 'tenant'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::resource('companies', CompanyController::class)->only(['index', 'store']);
    Route::get('contacts/import', [ContactImportController::class, 'create'])->name('contacts.import.create');
    Route::post('contacts/import/preview', [ContactImportController::class, 'preview'])->name('contacts.import.preview');
    Route::post('contacts/import', [ContactImportController::class, 'store'])->name('contacts.import.store');
    Route::resource('contacts', ContactController::class)->except(['create']);
    Route::get('pipeline-settings', [PipelineController::class, 'index'])->name('pipelines.index');
    Route::post('pipeline-settings', [PipelineController::class, 'store'])->name('pipelines.store');
    Route::put('leads/{lead}/stage', [LeadStageController::class, 'update'])->name('leads.stage.update');
    Route::resource('leads', LeadController::class)->except(['destroy']);
    Route::get('team', [TeamController::class, 'index'])->name('team.index');
    Route::post('team/invitations', [InvitationController::class, 'store'])->name('team.invitations.store');
    Route::delete('team/invitations/{invitation}', [InvitationController::class, 'destroy'])->name('team.invitations.destroy');
    Route::put('team/members/{user}', [TeamMemberController::class, 'update'])->name('team.members.update');
});

require __DIR__.'/settings.php';
