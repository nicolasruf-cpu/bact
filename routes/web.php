<?php

use App\Modules\Audit\Http\Controllers\AuditLogController;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Http\Controllers\ActiveSiteController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::put('active-site', [ActiveSiteController::class, 'update'])->name('active-site.update');

    Route::get('audit', [AuditLogController::class, 'index'])
        ->middleware('can:'.Permission::AuditView->value)
        ->name('audit.index');
});

require __DIR__.'/settings.php';
